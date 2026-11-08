<?php
/**
 * QA fixtures of the declarative routes (2.19.0): a webhook capability (Route::webhook) and a capability with a file
 * input (the `file` rule, through Route::form and Route::capability)
 */

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

#[Capability(name: 'qa_record_stripe', description: 'Records a Stripe event (QA webhook)', scope: 'payments:write')]
class QaRecordStripe extends Service
{
    public $rules = array('event' => 'required');

    public function handle(array $input, Context $context)
    {
        if (($input['event']['type'] ?? '') === 'qa.boom') {
            throw new RuntimeException('QA webhook failure');
        }
        return array('type' => $input['event']['type'] ?? null, 'actor' => $context->actorType());
    }
}

#[Capability(name: 'qa_set_cover', description: 'Sets a cover image (QA file input)', scope: 'books:write')]
class QaSetCover extends Service
{
    public $rules = array('cover' => 'required|file|extension,png;jpg|max_size,100kb', 'label' => 'max_len,20');

    public function handle(array $input, Context $context)
    {
        return array('cover' => $input['cover'], 'label' => $input['label'] ?? null);
    }
}
