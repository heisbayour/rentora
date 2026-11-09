<?php

declare(strict_types=1);

namespace App\Models\Contracts;

use App\Models\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Implemented by models using the HasDocuments trait. */
interface Documentable
{
    /** @return MorphMany<Document, covariant Model> */
    public function documents(): MorphMany;

    public function getMorphClass();

    public function getKey();
}
