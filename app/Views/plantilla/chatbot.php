<?php

if (session()->get('auth_role') === 'cliente' && (int) session()->get('cliente_id') > 0):
    $verCss = (string) @filemtime(FCPATH . 'css/chatbot.css');
    $verJs  = (string) @filemtime(FCPATH . 'js/chatbot.js');

    // Iconos (SVG en línea: el widget no depende de ninguna librería externa)
    $ico = [
        'chat'    => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.5-4.7A8 8 0 1 1 21 12Z"/>',
        'reloj'   => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/>',
        'atras'   => '<path d="M19 12H5"/><path d="m11 18-6-6 6-6"/>',
        'nuevo'   => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/>',
        'ampliar' => '<path d="M15 3h6v6"/><path d="M9 21H3v-6"/><path d="m21 3-7 7"/><path d="m3 21 7-7"/>',
        'minimizar' => '<path d="M5 12h14"/>',
        'enviar'  => '<path d="M12 19V5"/><path d="m5 12 7-7 7 7"/>',
    ];
    $svg = static fn (string $n, string $cls = ''): string =>
        '<svg class="fvbot-ico ' . $cls . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $ico[$n] . '</svg>';
?>
<link rel="stylesheet" href="<?= base_url('css/chatbot.css') ?>?v=<?= esc($verCss, 'attr') ?>">

<div id="fvbot" class="fvbot" data-vista="bienvenida">
    <button type="button" class="fvbot-launcher" id="fvbot-launcher" aria-expanded="false" aria-controls="fvbot-panel" aria-label="Abrir asistente de Fruver">
        <?= $svg('chat') ?>
        <span class="fvbot-launcher-text">¿Necesitas ayuda?</span>
    </button>

    <section class="fvbot-panel" id="fvbot-panel" role="dialog" aria-label="Asistente de Fruver" hidden>
        <header class="fvbot-top">
            <button type="button" class="fvbot-iconbtn" id="fvbot-btn-historial" aria-label="Historial de chat">
                <?= $svg('reloj', 'fvbot-ico-hist') ?><?= $svg('atras', 'fvbot-ico-back') ?>
            </button>
            <button type="button" class="fvbot-iconbtn" id="fvbot-btn-nuevo" aria-label="Nueva conversación"><?= $svg('nuevo') ?></button>
            <span class="fvbot-top-title" aria-hidden="true">Asistente Fruver</span>
            <button type="button" class="fvbot-iconbtn fvbot-only-desktop" id="fvbot-btn-ampliar" aria-label="Ampliar ventana" aria-pressed="false"><?= $svg('ampliar') ?></button>
            <button type="button" class="fvbot-iconbtn" id="fvbot-btn-cerrar" aria-label="Minimizar asistente"><?= $svg('minimizar') ?></button>
        </header>

        <!-- Vista: bienvenida -->
        <div class="fvbot-view" data-view="bienvenida">
            <div class="fvbot-hero">
                <div class="fvbot-hero-copy">
                    <h2 id="fvbot-saludo">¡Hola!</h2>
                    <p>Puedo ayudarte a consultar el estado de tus pedidos, ver promociones y resolver dudas sobre compras y entregas.</p>
                </div>
                <div class="fvbot-cards" id="fvbot-cards" aria-hidden="true"></div>
            </div>
            <div class="fvbot-chips" id="fvbot-chips-inicio" role="group" aria-label="Accesos rápidos"></div>
        </div>

        <!-- Vista: conversación -->
        <div class="fvbot-view fvbot-chat" data-view="chat" hidden>
            <div class="fvbot-msgs" id="fvbot-msgs" role="log" aria-live="polite" aria-relevant="additions"></div>
        </div>

        <!-- Vista: historial de chat -->
        <div class="fvbot-view fvbot-hist" data-view="historial" hidden>
            <h2>Historial de chat</h2>
            <div id="fvbot-hist-lista"></div>
            <div class="fvbot-hist-actions">
                <button type="button" class="fvbot-btn fvbot-btn--dark" id="fvbot-hist-nuevo"><?= $svg('nuevo') ?> Iniciar un chat nuevo</button>
                <button type="button" class="fvbot-link" id="fvbot-hist-borrar" hidden>Borrar historial</button>
            </div>
        </div>

        <form class="fvbot-input" id="fvbot-form" autocomplete="off">
            <label class="fvbot-sr" for="fvbot-texto">Escribe tu pregunta</label>
            <input type="text" id="fvbot-texto" maxlength="200" placeholder="Pregunta cualquier cosa…">
            <button type="submit" class="fvbot-send" aria-label="Enviar"><?= $svg('enviar') ?></button>
        </form>
    </section>
</div>

<script>window.FVBOT = { base: '<?= esc(rtrim(base_url(), '/') . '/', 'js') ?>', clienteId: <?= (int) session()->get('cliente_id') ?> };</script>
<script src="<?= base_url('js/chatbot.js') ?>?v=<?= esc($verJs, 'attr') ?>" defer></script>
<?php endif; ?>