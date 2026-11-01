{{-- Por dónde se creó el turno: Telegram o WhatsApp (bot) o Panel --}}
@if ($origen === 'telegram')
    <i class="bi bi-telegram" style="color: #229ED9"></i> Telegram
@elseif ($origen === 'whatsapp')
    <i class="bi bi-whatsapp text-success"></i> WhatsApp
@else
    <i class="bi bi-display text-secondary"></i> Panel
@endif
