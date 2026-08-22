import re

with open("tests/Feature/AdminP1PagesTest.php", "r") as f:
    code = f.read()

old_logic = """    // Validate candidate
    $component->call('validateCandidate', $candidate->id);
    $validatedCandidate = DB::table('parser_ai_candidates')->where('id', $candidate->id)->first();
    expect($validatedCandidate->status)->toBe('VALID');"""

new_logic = """    // Validate candidate
    \Illuminate\Support\Facades\Redis::lpush("queue:parser_validation:results:{$candidate->id}", json_encode([
        'is_valid' => true,
        'coverage_score' => 0.95,
        'field_results' => []
    ]));
    $component->call('validateCandidate', $candidate->id);
    $validatedCandidate = DB::table('parser_ai_candidates')->where('id', $candidate->id)->first();
    expect($validatedCandidate->status)->toBe('VALID');"""

code = code.replace(old_logic, new_logic)

with open("tests/Feature/AdminP1PagesTest.php", "w") as f:
    f.write(code)
