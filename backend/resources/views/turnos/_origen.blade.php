{{-- Por dónde se creó el turno: WhatsApp (bot) o Panel --}}
@if ($origen === 'whatsapp')
    <i class="bi bi-whatsapp text-success"></i> WhatsApp
@else
    <i class="bi bi-display text-secondary"></i> Panel
@endif
