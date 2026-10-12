{{-- Same-origin iframe, so it can grow with the lesson (steps change height) instead of cutting it off. --}}
<iframe
    src="{{ route('pratonton', $getRecord()) }}"
    title="{{ __('Pratonton') }} {{ $getRecord()->title }}"
    style="display: block; width: 100%; min-height: 640px; border: 0; border-radius: 12px"
    x-data
    x-on:cf-preview-step.window="$el.src = '{{ route('pratonton', $getRecord()) }}?langkah=' + $event.detail.n; $el.scrollIntoView({ behavior: 'smooth', block: 'start' })"
    x-init="
        const fit = () => { try { $el.style.height = $el.contentDocument.documentElement.scrollHeight + 'px' } catch (e) {} };
        fit();
        $el.addEventListener('load', () => { fit(); try { new ResizeObserver(fit).observe($el.contentDocument.body) } catch (e) {} });
    "
></iframe>
