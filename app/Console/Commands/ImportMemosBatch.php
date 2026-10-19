<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Memo;
use App\Group;
use App\Http\Traits\FileTrait;

// One-time bulk importer for a folder of circular/memo PDFs. Takes a JSON
// mapping keyed by the exact original filename, each value
// {date, name_ar, name_en, source_dir}, where source_dir is a
// subdirectory of {source_root} holding that specific file (this batch
// mixes two different folders: the redesigned تعميم PDFs and the
// original, untouched مذكرة PDFs). Every imported memo is assigned to
// the "All Members" group and never fires a push notification - this is
// historical backfill, not a new announcement.
class ImportMemosBatch extends Command
{
    use FileTrait;

    protected $signature = 'memos:import-batch
        {source_root : Directory containing the source_dir subfolders referenced in the mapping}
        {mapping : Path to the JSON mapping file}
        {group_id=81 : groups.group_id to assign every imported memo to (default 81 = All Members)}';

    protected $description = 'Bulk-import PDFs into memos using a filename => {date, name_ar, name_en, source_dir} JSON mapping';

    public function handle()
    {
        $sourceRoot = rtrim($this->argument('source_root'), '/\\');
        $mappingPath = $this->argument('mapping');
        $groupId = (int) $this->argument('group_id');

        if (!file_exists($mappingPath)) {
            $this->error("Mapping file not found: $mappingPath");
            return 1;
        }
        if (!Group::where('group_id', $groupId)->exists()) {
            $this->error("Group id $groupId not found in groups table - aborting before importing anything.");
            return 1;
        }

        $mapping = json_decode(file_get_contents($mappingPath), true);
        if (!is_array($mapping)) {
            $this->error('Mapping file is not valid JSON.');
            return 1;
        }

        $imported = 0;
        $skipped = [];

        foreach ($mapping as $originalFilename => $meta) {
            $fullPath = $sourceRoot . DIRECTORY_SEPARATOR . $meta['source_dir'] . DIRECTORY_SEPARATOR . $originalFilename;
            if (!file_exists($fullPath)) {
                $skipped[] = $originalFilename;
                $this->warn("Missing file, skipped: $originalFilename");
                continue;
            }

            $fileName = time() . '_' . $this->slugify($originalFilename);
            $bytes = file_get_contents($fullPath);

            if (env('FORCE_S3_STORAGE', config('app.env') != 'local')) {
                Storage::disk('s3')->put(
                    config('app.aws_bucket_project_name') . '/storage/images/memos/' . $fileName,
                    $bytes
                );
            } else {
                file_put_contents(public_path('images/memos/' . $fileName), $bytes);
            }

            $memo = new Memo();
            $memo->setTranslations('name', [
                'en' => $meta['name_en'],
                'ar' => $meta['name_ar'],
            ]);
            $memo->date = $meta['date'];
            $memo->file = $fileName;
            $memo->save();

            $memo->groups()->sync([$groupId]);

            $imported++;
            $this->info("Imported: $originalFilename -> id {$memo->id}");

            // keeps generated filenames unique even when several files
            // import within the same second (time() has 1s resolution).
            usleep(1100000);
        }

        $this->info("Done. Imported $imported / " . count($mapping) . ".");
        if ($skipped) {
            $this->warn('Skipped (file not found): ' . implode(', ', $skipped));
        }

        return 0;
    }
}
