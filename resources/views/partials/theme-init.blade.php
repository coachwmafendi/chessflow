{{-- Apply the saved colour theme before first paint (same key as Flux: flux.appearance; see resources/ts/chessflow/core/theme.ts). --}}
<script>
    (function () {
        try {
            var t = localStorage.getItem('flux.appearance');
            if (t === 'light' || t === 'dark') document.documentElement.dataset.theme = t;
        } catch (e) {}
    })();
</script>
