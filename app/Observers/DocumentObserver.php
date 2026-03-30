<?php

namespace App\Observers;

use App\CoreFacturalo\Requests\Inputs\Functions;
use App\Models\Tenant\Company;
use App\Models\Tenant\Document;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DocumentObserver
{
    public function creating(Document $document)
    {
        $company = Company::active();
        $number = Functions::newNumber($document->soap_type_id,
                                       $document->document_type_id,
                                       $document->series,
                                       $document->number, Document::class);
        $document->number = $number;

        $document->filename = Functions::filename($company, $document->document_type_id, $document->series, $number);
        $document->unique_filename = $document->filename;
    }

    public function created(Document $document)
    {
        $this->audit('created', $document);
    }

    public function updated(Document $document)
    {
        // Solo loguear si cambiaron campos sensibles
        $watched = ['state_type_id', 'total', 'series', 'number', 'soap_type_id'];
        $dirty = array_intersect(array_keys($document->getDirty()), $watched);

        if (!empty($dirty)) {
            $this->audit('updated', $document, [
                'changed_fields' => $dirty,
                'old' => array_intersect_key($document->getOriginal(), array_flip($dirty)),
                'new' => array_intersect_key($document->getDirty(),    array_flip($dirty)),
            ]);
        }
    }

    public function deleted(Document $document)
    {
        $this->audit('deleted', $document);
    }

    public function restored(Document $document)
    {
        $this->audit('restored', $document);
    }

    public function forceDeleted(Document $document)
    {
        $this->audit('force_deleted', $document);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function audit(string $event, Document $document, array $extra = []): void
    {
        try {
            Log::channel('daily')->info('document.audit', array_merge([
                'event'       => $event,
                'document_id' => $document->id,
                'type'        => $document->document_type_id ?? null,
                'series'      => $document->series,
                'number'      => $document->number,
                'total'       => $document->total ?? null,
                'state'       => $document->state_type_id ?? null,
                'user_id'     => Auth::id(),
                'user_name'   => Auth::user()?->name,
                'ip'          => request()?->ip(),
                'timestamp'   => now()->toISOString(),
            ], $extra));
        } catch (\Throwable) {
            // Nunca dejar que el log rompa la operación principal
        }
    }
}
