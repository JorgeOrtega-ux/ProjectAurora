<?php
?>
<div class="page-header">
    <h1>Enviar Comentarios</h1>
</div>
<div style="padding: 20px; max-width: 600px;">
    <form onsubmit="event.preventDefault(); alert('Gracias por tu feedback (Simulado)');">
        <div style="margin-bottom: 16px;">
            <label style="display:block; margin-bottom:8px; font-weight:500;">Asunto</label>
            <input type="text" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;" placeholder="Resumen del problema">
        </div>
        <div style="margin-bottom: 16px;">
            <label style="display:block; margin-bottom:8px; font-weight:500;">Mensaje</label>
            <textarea style="width:100%; height:120px; padding:10px; border:1px solid #ddd; border-radius:8px;" placeholder="Cuéntanos más detalles..."></textarea>
        </div>
        <button type="submit" style="background:#000; color:#fff; border:none; padding:10px 24px; border-radius:8px; cursor:pointer;">Enviar</button>
    </form>
</div>