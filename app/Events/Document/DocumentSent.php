<?php

namespace App\Events\Document;

use App\Abstracts\Event;
use App\Models\Document\Document;

class DocumentSent extends Event
{
    /**
     * @var Document
     */
    public $document;

    /**
     * Create a new event instance.
     *
     * @param $document
     */
    public function __construct($document)
    {
        $this->document = $document;
    }
}
