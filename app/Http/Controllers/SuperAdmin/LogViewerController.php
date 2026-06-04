<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Response;

class LogViewerController extends Controller
{
    public function index(): Response
    {
        $path = storage_path('logs/car4u.log');
        
        if (!File::exists($path)) {
            return response('Log file does not exist.', 404);
        }

        $content = File::get($path);
        // Get last 200 lines to keep it manageable
        $lines = explode("\n", $content);
        $lastLines = array_slice($lines, -200);
        $output = implode("\n", $lastLines);

        return response('<pre style="background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 8px; font-family: monospace; white-space: pre-wrap;">' . htmlspecialchars($output) . '</pre>');
    }
}
