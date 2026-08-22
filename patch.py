import re

with open("app/Livewire/Admin/Parser/Index.php", "r") as f:
    code = f.read()

# Replace the shell_exec logic with Redis enqueue & block
old_logic = """            // Execute real Python Validation Engine
            $selectorsJson = $candidate->candidate_selectors;
            $scriptPath = base_path('python_scraper/validator.py');
            $escapedInput = escapeshellarg($selectorsJson);
            $pythonCmd = "python3 {$scriptPath} {$escapedInput}";

            $output = @shell_exec($pythonCmd);
            $validation = json_decode($output, true);

            if (!$validation || !isset($validation['is_valid'])) {
                // In-process fallback MUST FAIL if python fails
                $validation = [
                    'is_valid' => false,
                    'coverage_score' => 0.0,
                    'field_results' => [],
                    'validator_engine' => 'PYTHON_VALIDATOR_FAILED'
                ];
                $status = 'FAILED';
                $isValid = false;
                $coveragePct = 0;
            } else {
                $isValid = $validation['is_valid'] ?? false;
                $coveragePct = intval(($validation['coverage_score'] ?? 0) * 100);
                $status = $isValid ? 'VALID' : 'INVALID';
            }"""

new_logic = """            $req = [
                'candidate_id' => $candidateId,
                'selectors' => json_decode($candidate->candidate_selectors, true)
            ];
            
            \Illuminate\Support\Facades\Redis::rpush('queue:parser_validation', json_encode($req));
            
            // Wait for response up to 5 seconds
            $res = \Illuminate\Support\Facades\Redis::blpop("queue:parser_validation:results:{$candidateId}", 5);
            
            $output = $res ? $res[1] : '';
            $validation = $res ? json_decode($output, true) : null;

            if (!$validation || !isset($validation['is_valid'])) {
                $validation = [
                    'is_valid' => false,
                    'coverage_score' => 0.0,
                    'field_results' => [],
                    'error' => 'Validation failed or timed out'
                ];
                $status = 'FAILED';
                $isValid = false;
                $coveragePct = 0;
            } else {
                $isValid = $validation['is_valid'] ?? false;
                $coveragePct = intval(($validation['coverage_score'] ?? 0) * 100);
                $status = $isValid ? 'VALID' : 'INVALID';
            }"""

code = code.replace(old_logic, new_logic)

with open("app/Livewire/Admin/Parser/Index.php", "w") as f:
    f.write(code)
