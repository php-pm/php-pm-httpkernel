<?php

namespace PHPPM\Tests\Fixtures;

class ProcessSlaveDouble
{
    private $watchedFiles = [];
    
    public function registerFile($file)
    {
        $this->watchedFiles[] = $file;
    }
}
