<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

Route::post('/webhook-dispatch', function (Request $request) {
    if ($request->header('X-Internal-Token') !== env('INTERNAL_API_TOKEN', 'secret-token')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    
    $jobId = $request->input('job_id');
    if ($jobId) {
        Artisan::call('webhook:dispatch', ['job_id' => $jobId]);
    }
    
    return response()->json(['success' => true]);
});
