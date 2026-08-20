<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Services\RunEngineService;
use App\Services\RunPreflightService;

class RunController extends Controller {
    public function getRun(Request $request, $run_id) {
        $orgId = $request->header('X-Organization-Id');
        if (!$orgId) return response()->json(['error' => 'Missing Org'], 400);
        $service = app(RunEngineService::class);
        $run = $service->getRunForOrganization($orgId, $run_id);
        if (!$run) return response()->json(['error' => 'Not found'], 404);
        return response()->json($run);
    }

    public function listRuns(Request $request) {
        $orgId = $request->header('X-Organization-Id');
        if (!$orgId) return response()->json(['error' => 'Missing Org'], 400);
        $runs = \App\Models\Run::where('organization_id', $orgId)->get();
        return response()->json($runs);
    }
    
    public function cancelRun(Request $request, $run_id) {
        $orgId = $request->header('X-Organization-Id');
        if (!$orgId) return response()->json(['error' => 'Missing Org'], 400);
        $service = app(RunEngineService::class);
        $run = $service->getRunForOrganization($orgId, $run_id);
        if (!$run) return response()->json(['error' => 'Not found'], 404);
        try {
            $run = $service->cancelRun($run);
            return response()->json($run);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
    public function createFacebookPosts(Request $request) {
        return $this->createGenericRun($request, 'facebook_posts');
    }
    
    private function createGenericRun(Request $request, $capability) {
        $orgId = $request->header('X-Organization-Id');
        if (!$orgId) return response()->json(['error' => 'Missing Org'], 400);
        
        $preflight = app(RunPreflightService::class);
        try {
            $preflight->validate($request->user(), $orgId, $capability);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
        
        $service = app(RunEngineService::class);
        $run = $service->createRun($orgId, $capability, $request->all());
        return response()->json($run, 201);
    }
}
