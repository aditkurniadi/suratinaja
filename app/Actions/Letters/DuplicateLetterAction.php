<?php

namespace App\Actions\Letters;

use App\Exceptions\DomainException;
use App\Models\Letter;
use App\Models\User;

class DuplicateLetterAction
{
    public function __construct(
        private readonly CreateLetterAction $creator,
    ) {}

    /**
     * Duplicates an existing letter into a brand-new `draft` letter with a newly
     * allocated sequential number and copied field_values.
     *
     * @throws DomainException
     */
    public function execute(Letter $source, User $actor): Letter
    {
        $workspace = $source->workspace()->withoutGlobalScopes()->firstOrFail();

        return $this->creator->execute($workspace, $actor, [
            'template_id' => $source->template_id,
            'perihal' => $source->perihal,
            'letter_date' => ($source->letter_date ?? now())->toDateString(),
            'variables' => $source->field_values ?? [],
            'duplicated_from_id' => $source->id,
        ]);
    }
}
