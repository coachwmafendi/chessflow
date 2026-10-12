@php
    use App\Support\LessonStepView;
    $lesson = $getRecord();
    $types = LessonStepView::typeLabels();
@endphp
{{-- Readable step list for checking content. Lesson text is trusted repo content (data/lessons.json), rendered as HTML like the island does. --}}
<style>
    .cf-steps{display:flex;flex-direction:column;gap:14px}
    .cf-step{display:grid;grid-template-columns:184px minmax(0,1fr);gap:18px;padding:16px;border:1px solid rgb(0 0 0 / .08);border-radius:14px;background:rgb(0 0 0 / .015)}
    .dark .cf-step{border-color:rgb(255 255 255 / .1);background:rgb(255 255 255 / .02)}
    @media (max-width:700px){.cf-step{grid-template-columns:minmax(0,1fr)}}
    .cf-mini{display:grid;grid-template-columns:repeat(8,1fr);width:184px;aspect-ratio:1;border:4px solid #1F3B36;border-radius:8px;overflow:hidden}
    .cf-mini span{position:relative;display:block}
    .cf-mini .l{background:#F1E6CF} .cf-mini .d{background:#7FAA9F}
    .cf-mini .hl::before{content:"";position:absolute;inset:0;background:rgb(255 201 77 / .55)}
    .cf-mini .from::before{content:"";position:absolute;inset:0;background:rgb(255 190 61 / .8)}
    .cf-mini .ring::after{content:"";position:absolute;inset:8%;border:2.5px solid #E5484D;border-radius:50%}
    .cf-mini .dot::after{content:"";position:absolute;inset:36%;background:rgb(0 0 0 / .3);border-radius:50%}
    .cf-mini .star::after{content:"★";position:absolute;inset:0;display:grid;place-items:center;color:#E8A500;font-size:15px;line-height:1}
    .cf-mini img{position:absolute;inset:4%;width:92%;height:92%}
    .cf-head{display:flex;flex-wrap:wrap;align-items:center;gap:8px 10px}
    .cf-n{display:grid;place-items:center;width:26px;height:26px;border-radius:50%;background:#087466;color:#fff;font-weight:700;font-size:13px}
    .cf-type{font-size:12px;font-weight:600;padding:2px 9px;border-radius:999px;background:rgb(8 116 102 / .1);color:#087466}
    .dark .cf-type{background:rgb(52 194 176 / .15);color:#34C2B0}
    .cf-title{font-weight:700;font-size:16px}
    .cf-go{margin-left:auto;font-size:13px;font-weight:600;color:#087466;background:none;border:1px solid rgb(8 116 102 / .35);border-radius:8px;padding:4px 10px;cursor:pointer}
    .dark .cf-go{color:#34C2B0;border-color:rgb(52 194 176 / .4)}
    .cf-say{margin-top:10px;font-size:14px;line-height:1.55}
    .cf-task{margin-top:8px;font-size:14px;padding:8px 12px;border-radius:10px;background:rgb(255 190 61 / .16)}
    .cf-task b:first-child{font-size:11px;letter-spacing:.08em;text-transform:uppercase;margin-right:6px;opacity:.7}
    .cf-dl{margin-top:10px;display:grid;grid-template-columns:max-content minmax(0,1fr);gap:6px 14px;font-size:14px}
    .cf-dl dt{color:rgb(0 0 0 / .55);font-weight:500} .dark .cf-dl dt{color:rgb(255 255 255 / .55)}
    .cf-dl dd{margin:0} .cf-dl ol{margin:0;padding-left:1.2em;list-style:decimal} .cf-dl ul.quiz{margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:4px}
    .cf-dl li.ok{color:#0B7A55;font-weight:600} .dark .cf-dl li.ok{color:#45C985}
    .cf-dl small{font-weight:400;opacity:.8}
    .cf-step code,.cf-step kbd{font-family:ui-monospace,monospace;font-size:12.5px;padding:1px 5px;border-radius:5px;background:rgb(0 0 0 / .06)}
    .dark .cf-step code,.dark .cf-step kbd{background:rgb(255 255 255 / .1)}
</style>
<div class="cf-steps">
    @foreach ($lesson->steps as $i => $step)
        @php $details = LessonStepView::details($step); @endphp
        <article class="cf-step" data-step="{{ $i + 1 }}">
            <div class="cf-mini" role="img" aria-label="{{ __('Papan langkah :n', ['n' => $i + 1]) }}">
                @foreach (LessonStepView::board($step) as $s)
                    <span @class([$s['light'] ? 'l' : 'd', 'hl' => $s['hl'], 'from' => $s['from'], 'ring' => $s['ring'], 'dot' => $s['dot'], 'star' => $s['star']]) title="{{ $s['name'] }}">
                        @if ($s['piece'])<img src="/images/pieces/{{ $s['piece'] }}.svg" alt="">@endif
                    </span>
                @endforeach
            </div>
            <div>
                <div class="cf-head">
                    <span class="cf-n">{{ $i + 1 }}</span>
                    <span class="cf-type">{{ $types[$step['type']] ?? $step['type'] }}</span>
                    <span class="cf-title">{{ $step['title'] ?? '' }}</span>
                    <button type="button" class="cf-go" x-data x-on:click="$dispatch('cf-preview-step', { n: {{ $i + 1 }} })">{{ __('Pratonton langkah ini') }}</button>
                </div>
                @if (! empty($step['say']))<p class="cf-say">{!! $step['say'] !!}</p>@endif
                @if (! empty($step['task']))<p class="cf-task"><b>{{ __('Tugas') }}</b>{!! $step['task'] !!}</p>@endif
                @if ($details)
                    <dl class="cf-dl">
                        @foreach ($details as [$label, $html])
                            <dt>{{ $label }}</dt><dd>{!! $html !!}</dd>
                        @endforeach
                    </dl>
                @endif
            </div>
        </article>
    @endforeach
</div>
