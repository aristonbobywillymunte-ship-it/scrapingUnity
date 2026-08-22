import re

with open("app/Livewire/Admin/Parser/Index.php", "r") as f:
    code = f.read()

old_logic = """                if ($response->successful()) {
                    $json = $response->json('choices.0.message.content');
                    $parsed = json_decode($json, true);
                    if (is_array($parsed)) {
                        $suggestedSelectors = array_merge($suggestedSelectors, $parsed);
                    }
                } else {
                    $aiProvider = 'OPENAI_FAILED';
                }
            } else {
                $aiProvider = 'LOCAL_HEURISTIC';
                $aiModel = 'heuristic_v1';
            }"""

new_logic = """                if ($response->successful()) {
                    $json = $response->json('choices.0.message.content');
                    $parsed = json_decode($json, true);
                    
                    // Validate AI JSON schema strictly
                    if (is_array($parsed)) {
                        $valid = true;
                        foreach ($parsed as $k => $v) {
                            if (!is_string($k) || !is_string($v)) $valid = false;
                        }
                        if ($valid) {
                            $suggestedSelectors = array_merge($suggestedSelectors, $parsed);
                        } else {
                            $aiProvider = 'LOCAL_HEURISTIC';
                        }
                    } else {
                        $aiProvider = 'LOCAL_HEURISTIC';
                    }
                } else {
                    $aiProvider = 'LOCAL_HEURISTIC';
                    $aiModel = 'heuristic_v1';
                }
            } else {
                $aiProvider = 'LOCAL_HEURISTIC';
                $aiModel = 'heuristic_v1';
            }"""

code = code.replace(old_logic, new_logic)

with open("app/Livewire/Admin/Parser/Index.php", "w") as f:
    f.write(code)
