<?php
namespace App\Livewire\Admin\Platforms;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use App\Livewire\Admin\Concerns\AuthorizesAdmin;

#[Layout('layouts.app')]
class Index extends Component {
    use AuthorizesAdmin;

    public function mount() {
        $this->authorizeAdmin();
    }

    public function render() {
        // Platform capability matrix based on PRD §6 & actual runtime
        $capabilities = [
            [
                'platform' => 'Facebook',
                'operation' => 'Profile',
                'status' => 'ACTIVE',
                'http_supported' => true,
                'browser_supported' => false,
                'session_required' => false,
                'max_items' => 50,
                'timeout_sec' => 30,
                'cache_ttl_sec' => 3600,
                'active_parser' => 'facebook_profile_v1',
            ],
            [
                'platform' => 'Facebook',
                'operation' => 'Single Post',
                'status' => 'ACTIVE',
                'http_supported' => true,
                'browser_supported' => false,
                'session_required' => false,
                'max_items' => 1,
                'timeout_sec' => 30,
                'cache_ttl_sec' => 3600,
                'active_parser' => 'facebook_single_post_v1',
            ],
            [
                'platform' => 'Facebook',
                'operation' => 'Profile Posts',
                'status' => 'ACTIVE',
                'http_supported' => true,
                'browser_supported' => false,
                'session_required' => false,
                'max_items' => 100,
                'timeout_sec' => 60,
                'cache_ttl_sec' => 1800,
                'active_parser' => 'facebook_profile_posts_v1',
            ],
            [
                'platform' => 'Facebook',
                'operation' => 'Replies / Comments',
                'status' => 'ACTIVE',
                'http_supported' => true,
                'browser_supported' => false,
                'session_required' => false,
                'max_items' => 100,
                'timeout_sec' => 60,
                'cache_ttl_sec' => 1800,
                'active_parser' => 'facebook_replies_v1',
            ],
            [
                'platform' => 'Instagram',
                'operation' => 'Profile / Posts',
                'status' => 'PHASE_2_PLANNED',
                'http_supported' => false,
                'browser_supported' => false,
                'session_required' => false,
                'max_items' => 0,
                'timeout_sec' => 0,
                'cache_ttl_sec' => 0,
                'active_parser' => 'N/A',
            ],
            [
                'platform' => 'Threads',
                'operation' => 'Profile / Posts',
                'status' => 'PHASE_2_PLANNED',
                'http_supported' => false,
                'browser_supported' => false,
                'session_required' => false,
                'max_items' => 0,
                'timeout_sec' => 0,
                'cache_ttl_sec' => 0,
                'active_parser' => 'N/A',
            ],
            [
                'platform' => 'X / Twitter',
                'operation' => 'Profile / Tweets',
                'status' => 'PHASE_3_PLANNED',
                'http_supported' => false,
                'browser_supported' => false,
                'session_required' => false,
                'max_items' => 0,
                'timeout_sec' => 0,
                'cache_ttl_sec' => 0,
                'active_parser' => 'N/A',
            ],
        ];

        return view('livewire.admin.platforms.index', [
            'capabilities' => $capabilities,
        ]);
    }
}
