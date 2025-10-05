<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;

class JsonController extends Controller
{
    protected $dbPath;
    protected $jsonData;

    public function __construct()
    {
        $this->dbPath = base_path('db.json');
        if (File::exists($this->dbPath)) {
            $this->jsonData = json_decode(File::get($this->dbPath), true);
        } else {
            $this->jsonData = [];
        }
    }

    protected function writeJson(array $data)
    {
        File::put($this->dbPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
