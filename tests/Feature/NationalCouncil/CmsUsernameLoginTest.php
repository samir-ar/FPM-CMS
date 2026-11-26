<?php

namespace Tests\Feature\NationalCouncil;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// CMS sign-in with a plain username as well as an e-mail address.
class CmsUsernameLoginTest extends CouncilTestCase
{
    private function account(string $login): int
    {
        return DB::table('users')->insertGetId(['name' => 'T login', 'email' => $login, 'password' => Hash::make('secret-123'), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_a_username_and_an_email_both_sign_in(): void
    {
        foreach (['zr.tvscreen', 'zr.person@example.org'] as $login) {
            $id = $this->account($login);
            $this->post('/login', ['email' => "  $login ", 'password' => 'secret-123'])->assertRedirect();
            $this->assertSame($id, auth('admin')->id(), $login);
            auth('admin')->logout();
        }
    }

    public function test_a_wrong_password_or_unknown_name_does_not_sign_in(): void
    {
        $this->account('zr.tvscreen');
        $this->post('/login', ['email' => 'zr.tvscreen', 'password' => 'wrong-123'])->assertRedirect();
        $this->assertGuest('admin');
        $this->post('/login', ['email' => 'zr.nobody', 'password' => 'secret-123'])->assertRedirect();
        $this->assertGuest('admin');
    }

    public function test_the_login_page_accepts_text_and_two_accounts_cannot_share_a_name(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();
        $this->assertStringContainsString('id="email" type="text"', $html);
        $this->assertStringContainsString('Username or E-Mail', $html);

        $this->account('zr.taken');
        $other = $this->account('zr.other');
        $this->actingAs($this->adminUser(), 'admin');
        $before = DB::table('users')->count();
        $this->from('/admin/admins/create')->post(route('admin.admins.store', [], false), ['name' => 'x', 'email' => 'zr.taken', 'password' => 'secret-123', 'password_confirmation' => 'secret-123'])
            ->assertSessionHasErrors('email');
        $this->assertSame($before, DB::table('users')->count());
        $this->from('/admin/admins')->put(route('admin.admins.update', $other, false), ['name' => 'x', 'email' => 'zr.taken'])->assertSessionHasErrors('email');
        // saving an account without changing its own name is fine
        $this->put(route('admin.admins.update', $other, false), ['name' => 'x2', 'email' => 'zr.other'])->assertSessionHasNoErrors();
        $this->post(route('admin.admins.store', [], false), ['name' => 'new', 'email' => 'zr.display', 'password' => 'secret-123', 'password_confirmation' => 'secret-123'])->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('users')->where('email', 'zr.display')->count());
    }
}
