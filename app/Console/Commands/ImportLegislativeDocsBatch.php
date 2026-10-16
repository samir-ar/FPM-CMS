<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\V2\LegislativeDoc;
use App\Http\Traits\FileTrait;

// One-time bulk importer for a folder of PDFs where the title/date per file
// isn't derivable from the filename alone (titles were extracted by hand
// from each PDF's actual الموضوع line). The mapping file is a JSON object
// keyed by the exact original filename, each value {title, date}. Reuses
// the same moveFile()-style upload convention (time()+slugify filename,
// uploaded to storage/legislative_docs on the configured disk) so these
// rows behave identically to ones created through the normal admin form.
class ImportLegislativeDocsBatch extends Command
{
    use FileTrait;

    protected $signature = 'legislative-docs:import-batch
        {category_id : legislative_doc_categories.id to assign every imported doc to}
        {source_dir : Directory containing the PDF files}
        {mapping : Path to the JSON mapping file (filename => {title, date})}';

    protected $description = 'Bulk-import PDFs into legislative_docs using a filename => {title, date} JSON mapping';

    public function handle()
    {
        $categoryId = $this->argument('category_id');
        $sourceDir = rtrim($this->argument('source_dir'), '/\\');
        $mappingPath = $this->argument('mapping');

        if (!is_dir($sourceDir)) {
            $this->error("Source directory not found: $sourceDir");
            return 1;
        }
        if (!file_exists($mappingPath)) {
            $this->error("Mapping file not found: $mappingPath");
            return 1;
        }

        $mapping = json_decode(file_get_contents($mappingPath), true);
        if (!is_array($mapping)) {
            $this->error("Mapping file is not valid JSON.");
            return 1;
        }

        $imported = 0;
        $skipped = [];

        foreach ($mapping as $originalFilename => $meta) {
            $fullPath = $sourceDir . DIRECTORY_SEPARATOR . $originalFilename;
            if (!file_exists($fullPath)) {
                $skipped[] = $originalFilename;
                $this->warn("Missing file, skipped: $originalFilename");
                continue;
            }

            $fileName = time() . '_' . $this->slugify($originalFilename);
            $bytes = file_get_contents($fullPath);

            if (env('FORCE_S3_STORAGE', config('app.env') != 'local')) {
                Storage::disk('s3')->put(
                    config('app.aws_bucket_project_name') . '/storage/legislative_docs/' . $fileName,
                    $bytes
                );
            } else {
                file_put_contents(public_path('legislative_docs/' . $fileName), $bytes);
            }

            $doc = new LegislativeDoc();
            $doc->category_id = $categoryId;
            $doc->subcategory_id = null;
            $doc->title = $meta['title'];
            $doc->date = $meta['date'];
            $doc->file_name = $fileName;
            $doc->order = 0;
            $doc->save();

            $imported++;
            $this->info("Imported: $originalFilename -> id {$doc->id}");

            // Guarantees a unique fileName even when multiple files import
            // within the same second (time() has only 1s resolution).
            usleep(1100000);
        }

        $this->info("Done. Imported $imported / " . count($mapping) . ".");
        if ($skipped) {
            $this->warn('Skipped (file not found in source_dir): ' . implode(', ', $skipped));
        }

        return 0;
    }
}
