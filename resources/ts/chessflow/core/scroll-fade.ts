// Soft fades on a scrolling list: at the top when there is more above, at the bottom when there is more below.
// The list is wrapped once; call the returned function after changing its content.

export function scrollFade(list: HTMLElement): () => void {
    let wrap = list.parentElement;
    if (!wrap?.classList.contains('scroll-fade')) {
        wrap = document.createElement('div');
        wrap.className = 'scroll-fade';
        list.replaceWith(wrap);
        wrap.append(list, Object.assign(document.createElement('span'), { className: 'fade-top', ariaHidden: 'true' }), Object.assign(document.createElement('span'), { className: 'fade-bottom', ariaHidden: 'true' }));
    }
    const box = wrap;
    const update = (): void => {
        const max = list.scrollHeight - list.clientHeight;
        box.classList.toggle('more-above', list.scrollTop > 2);
        box.classList.toggle('more-below', max - list.scrollTop > 2);
    };
    list.addEventListener('scroll', update, { passive: true });
    update();
    return update;
}
