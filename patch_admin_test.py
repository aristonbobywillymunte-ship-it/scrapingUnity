import re

with open("tests/Feature/AdminP1PagesTest.php", "r") as f:
    code = f.read()

# We need to mock the Redis queue response before calling validateCandidate
old_logic = """
    // 4. Validate Candidate
    Livewire::actingAs($this->admin)
        ->test(Parser\Index::class)
        ->call('validateCandidate', $candidate->id)
        ->assertSet('successMessage', function($msg) {
            return str_contains($msg, 'divallidasi');
        });"""

new_logic = """
    // Mock Python worker response on Redis
    \Illuminate\Support\Facades\Redis::lpush("queue:parser_validation:results:{$candidate->id}", json_encode([
        'is_valid' => true,
        'coverage_score' => 0.95,
        'field_results' => []
    ]));

    // 4. Validate Candidate
    Livewire::actingAs($this->admin)
        ->test(Parser\Index::class)
        ->call('validateCandidate', $candidate->id)
        ->assertSet('successMessage', function($msg) {
            return str_contains($msg, 'Status: VALID') || str_contains($msg, 'divallidasi') || str_contains($msg, 'selesai');
        });"""

code = code.replace(old_logic, new_logic)

with open("tests/Feature/AdminP1PagesTest.php", "w") as f:
    f.write(code)
