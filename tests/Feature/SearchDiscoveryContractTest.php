<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Task;
use App\Models\Run;
use Livewire\Livewire;
use App\Livewire\Runs\Create;
use Illuminate\Support\Facades\Artisan;

class SearchDiscoveryContractTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 'owner', 'description' => 'Owner', 'is_internal_role' => false],
            ['id' => 'member', 'description' => 'Member', 'is_internal_role' => false],
        ]);

        $this->user = User::create([
            'id' => Str::uuid(),
            'email' => 'discovery_tester@example.com',
            'password_hash' => Hash::make('secret123'),
            'status' => 'ACTIVE'
        ]);

        $this->org = Organization::create([
            'id' => Str::uuid(),
            'name' => 'Discovery Org',
            'status' => 'ACTIVE'
        ]);

        OrganizationMembership::create([
            'id' => Str::uuid(),
            'user_id' => $this->user->id,
            'organization_id' => $this->org->id,
            'role_id' => 'owner',
            'role_is_internal' => false
        ]);

        DB::table('credit_lots')->insert([
            'id' => Str::uuid(),
            'organization_id' => $this->org->id,
            'original_quantity' => 1000.0,
            'remaining_quantity' => 1000.0,
            'source' => 'TOP_UP',
            'expires_at' => now()->addYear()
        ]);

        $this->actingAs($this->user);
        $this->withSession(['organization_id' => $this->org->id]);
        $this->withHeader('X-Organization-Id', $this->org->id);
    }

    /** 1. Search query accepted for supported discovery capability */
    public function test_search_query_accepted_for_posts()
    {
        Livewire::test(Create::class)
            ->set('capability', 'facebook_posts')
            ->set('discovery_mode', 'search_query')
            ->set('search_query', 'politik indonesia')
            ->set('max_pages', 2)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('error', '');

        $run = Run::first();
        $this->assertNotNull($run);
        $this->assertNotNull($run->request);
        $this->assertEquals('search_query', $run->request->options['discovery_mode']);
        $this->assertEquals('politik indonesia', $run->request->options['search_query']);

        $task = Task::first();
        $this->assertNotNull($task);
    }

    /** 2. Hashtag accepted and normalized for supported capability */
    public function test_hashtag_accepted_and_normalized()
    {
        Livewire::test(Create::class)
            ->set('capability', 'instagram_reels')
            ->set('discovery_mode', 'hashtag')
            ->set('hashtag', '#trending')
            ->set('max_pages', 1)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('error', '');

        $run = Run::first();
        $this->assertNotNull($run);
        $this->assertEquals('hashtag', $run->request->options['discovery_mode']);
        $this->assertEquals('trending', $run->request->options['hashtag'], 'Leading hash must be normalized');
    }

    /** 3. Malformed hashtag rejected */
    public function test_malformed_hashtag_rejected()
    {
        Livewire::test(Create::class)
            ->set('capability', 'instagram_reels')
            ->set('discovery_mode', 'hashtag')
            ->set('hashtag', '   #   ')
            ->call('submit')
            ->assertSet('error', 'Hashtag tidak valid.');
    }

    /** 4. Hashtag rejected for capability that does not support it (e.g. web_pages) */
    public function test_hashtag_rejected_for_unsupported_capability()
    {
        Livewire::test(Create::class)
            ->set('capability', 'web_pages')
            ->set('discovery_mode', 'hashtag')
            ->set('hashtag', 'tech')
            ->call('submit')
            ->assertSet('error', 'Mode pencarian hashtag tidak didukung untuk capability web_pages.');
    }

    /** 5. Comment capability requires parent target and rejects empty target */
    public function test_comment_capability_requires_target()
    {
        Livewire::test(Create::class)
            ->set('capability', 'facebook_comments')
            ->set('discovery_mode', 'target')
            ->set('target', '')
            ->call('submit')
            ->assertHasErrors(['target']);
    }

    /** 6. Comment capability rejects search_query or hashtag modes */
    public function test_comment_capability_rejects_query_and_hashtag_modes()
    {
        Livewire::test(Create::class)
            ->set('capability', 'youtube_comments')
            ->set('discovery_mode', 'search_query')
            ->set('search_query', 'my keyword')
            ->call('submit')
            ->assertSet('error', 'Mode pencarian search_query tidak didukung untuk capability youtube_comments.');

        Livewire::test(Create::class)
            ->set('capability', 'youtube_comments')
            ->set('discovery_mode', 'hashtag')
            ->set('hashtag', 'myhashtag')
            ->call('submit')
            ->assertSet('error', 'Mode pencarian hashtag tidak didukung untuk capability youtube_comments.');
    }

    /** 7. End-to-end pipeline: Task executes and produces deduplicated result for query discovery */
    public function test_discovery_pipeline_execution_and_canonical_dedupe()
    {
        // Run 1: Query discovery
        Livewire::test(Create::class)
            ->set('capability', 'facebook_posts')
            ->set('discovery_mode', 'search_query')
            ->set('search_query', 'pemilu 2026')
            ->call('submit');

        Artisan::call('queue:work', ['--stop-when-empty' => true]);

        $task1 = Task::first();
        $this->assertEquals('COMPLETED', $task1->status);

        $entitiesCount = DB::table('canonical_entities')->count();
        $this->assertEquals(1, $entitiesCount);

        // Run 2: Hashtag discovery for same underlying entity (same stable_source_id in collector fixture)
        Livewire::test(Create::class)
            ->set('capability', 'facebook_posts')
            ->set('discovery_mode', 'hashtag')
            ->set('hashtag', '#pemilu2026')
            ->call('submit');

        Artisan::call('queue:work', ['--stop-when-empty' => true]);

        // Entity count remains 1 due to canonical deduplication
        $entitiesCountAfter = DB::table('canonical_entities')->count();
        $this->assertEquals(1, $entitiesCountAfter, 'Canonical entity must be deduplicated across discovery modes');
    }
}
