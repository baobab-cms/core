{{-- Rendu seulement en environnement local (spec 10 §3.4) — en production, le widget est simplement omis. --}}
<div class="widget-error" style="border:1px dashed red;padding:.5rem;color:red;">
    {{ $data['message'] }}
</div>
