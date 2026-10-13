<?php

namespace App\Core;

use PDO;

abstract class Model
{
    protected $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    /**
     * Get the PDO connection
     */
    protected function getDb()
    {
        return $this->db;
    }
}

