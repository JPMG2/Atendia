@use('Carbon\CarbonImmutable')

@props([
    'year',
    'holidays', // Collection<int, array{date: CarbonImmutable, name: string, kind: string}>
    'country' => '',
    'actionable' => false, // a weekday is a button: a click marks it as a one-year holiday (or switches it off)
])

{{--
    A country's year: twelve months, and the same days in words below (a phone
    has no hover). Tuesday and Thursday holidays are flagged as a possible bridge.
    Only days with an action are buttons: an empty weekday, and a one-year
    holiday to switch off. A click, or a drag over several days, asks first:
    it changes every business there. The mail to them is a second question.
--}}
@php
    $byDate = $holidays->keyBy(fn (array $holiday): string => $holiday['date']->format('Y-m-d'));
    $weekdays = __('catalog.country_holiday.calendar.weekdays');
    $isBridge = fn (CarbonImmutable $date): bool => in_array($date->dayOfWeekIso, [2, 4], true);
    $onWeekend = $holidays->filter(fn (array $holiday): bool => $holiday['date']->isWeekend())->count();
    $words = [
        'create' => [
            'title' => __('catalog.country_holiday.bridge.create_title'),
            'message' => __('catalog.country_holiday.bridge.create_message', ['country' => $country, 'year' => $year]),
            'accept' => __('catalog.country_holiday.bridge.create_accept'),
        ],
        'off' => [
            'title' => __('catalog.country_holiday.bridge.off_title'),
            'message' => __('catalog.country_holiday.bridge.off_message', ['country' => $country]),
            'accept' => __('catalog.country_holiday.bridge.off_accept'),
        ],
        'range' => [
            'one' => __('catalog.country_holiday.bridge.range_title_one'),
            'many' => __('catalog.country_holiday.bridge.range_title_many'),
            'message' => __('catalog.country_holiday.bridge.range_message', ['country' => $country]),
            'accept' => __('catalog.country_holiday.bridge.range_accept'),
        ],
    ];
@endphp

<div
    class="ycal"
    @if ($actionable)
        x-data="{
            words: {{ \Illuminate\Support\Js::from($words) }},
            drag: null,
            async ask(date, isOn) {
                const words = this.words[isOn ? 'off' : 'create'];
                const ok = await dialog.confirm({
                    title: words.title.replace(':date', this.show(date)),
                    message: words.message,
                    accept: words.accept,
                });

                if (ok) {
                    this.offer(await $wire.toggleBridge(date));
                }
            },
            async offer(found) {
                if (found && await dialog.confirm({ title: found.title, message: found.message, accept: found.accept })) {
                    $wire.noticeBridge(found.marked);
                }
            },
            show(date) {
                return date.split('-').reverse().join('/');
            },
            begin(event) {
                const cell = event.button === 0 ? event.target.closest('[data-free]') : null;

                this.drag = cell ? { from: cell.dataset.date, to: cell.dataset.date, moved: false } : null;
            },
            move(event) {
                const cell = this.drag && document.elementFromPoint(event.clientX, event.clientY)?.closest('[data-date]');

                if (cell && this.$el.contains(cell) && cell.dataset.date !== this.drag.to) {
                    this.drag.to = cell.dataset.date;
                    this.drag.moved = this.drag.to !== this.drag.from;
                    this.paint();
                }
            },
            paint() {
                const [a, b] = [this.drag.from, this.drag.to].sort();

                this.$el.querySelectorAll('[data-date]').forEach((cell) => {
                    cell.classList.toggle('is-pick', this.drag.moved && cell.dataset.date >= a && cell.dataset.date <= b);
                });
            },
            async finish() {
                const drag = this.drag;

                this.drag = null;
                this.$el.querySelectorAll('.is-pick').forEach((cell) => cell.classList.remove('is-pick'));

                if (! drag || ! drag.moved) {
                    return;
                }

                const [a, b] = [drag.from, drag.to].sort();
                const free = [...this.$el.querySelectorAll('[data-free]')]
                    .filter((cell) => cell.dataset.date >= a && cell.dataset.date <= b).length;
                const words = this.words.range;
                const ok = free > 0 && await dialog.confirm({
                    title: (free === 1 ? words.one : words.many).replace(':count', free),
                    message: words.message.replace(':from', this.show(a)).replace(':to', this.show(b)),
                    accept: words.accept,
                });

                if (ok) {
                    this.offer(await $wire.bridgeRange(a, b));
                }
            },
        }"
        x-on:pointerdown="begin($event)"
        x-on:pointermove="move($event)"
        x-on:pointerup.window="finish()"
        x-on:pointercancel.window="drag = null"
    @endif
>
    <div class="ycal-months">
        @foreach (range(1, 12) as $month)
            @php
                $first = CarbonImmutable::create($year, $month, 1);
                $lead = $first->dayOfWeekIso - 1;
            @endphp

            <section
                class="ycal-month"
                wire:key="ycal-{{ $year }}-{{ $month }}"
                aria-label="{{ __('catalog.country_holiday.months.'.$month) }}"
            >
                <h3 class="ycal-title">{{ __('catalog.country_holiday.months.'.$month) }}</h3>

                <div class="ycal-grid" @if (! $actionable) aria-hidden="true" @endif>
                    @foreach ($weekdays as $letter)
                        <span class="ycal-dow">{{ $letter }}</span>
                    @endforeach

                    @for ($blank = 0; $blank < $lead; $blank++)
                        <span></span>
                    @endfor

                    @for ($day = 1; $day <= $first->daysInMonth; $day++)
                        @php
                            $date = $first->setDay($day);
                            $holiday = $byDate->get($date->format('Y-m-d'));
                        @endphp
                        @php
                            $once = $holiday !== null && $holiday['kind'] === 'once';
                            $canAct = $actionable && ($once || ($holiday === null && ! $date->isWeekend()));
                            $classes = [
                                'ycal-day',
                                'is-weekend' => $date->isWeekend(),
                                'is-holiday' => $holiday !== null,
                                'is-bridge' => $holiday !== null && $isBridge($date),
                                'is-action' => $canAct,
                            ];
                        @endphp

                        @if ($canAct)
                            <button
                                type="button"
                                @class($classes)
                                data-date="{{ $date->format('Y-m-d') }}"
                                @if ($holiday === null) data-free @endif
                                x-on:click="ask('{{ $date->format('Y-m-d') }}', {{ $once ? 'true' : 'false' }})"
                                aria-label="{{ $date->format('d/m/Y') }}{{ $holiday !== null ? ': '.$holiday['name'] : '' }}"
                                @if ($holiday !== null) title="{{ $holiday['name'] }}" @endif
                            >
                                {{ $day }}
                            </button>
                        @else
                            <span
                                @class($classes)
                                @if ($actionable) data-date="{{ $date->format('Y-m-d') }}" @endif
                                @if ($holiday !== null) title="{{ $holiday['name'] }}" @endif
                            >{{ $day }}</span>
                        @endif
                    @endfor
                </div>
            </section>
        @endforeach
    </div>

    <p class="ycal-legend">
        <span class="ycal-key is-holiday"></span> {{ __('catalog.country_holiday.calendar.holiday') }}
        <span class="ycal-key is-holiday is-bridge"></span> {{ __('catalog.country_holiday.calendar.bridge') }}
    </p>

    @if ($holidays->isEmpty())
        <p class="text-muted text-sm">{{ __('catalog.country_holiday.calendar.none', ['year' => $year]) }}</p>
    @else
        <p class="ycal-summary">
            {{
                trans_choice('catalog.country_holiday.calendar.summary', $holidays->count(), [
                    'count' => $holidays->count(),
                    'weekend' => $onWeekend,
                    'year' => $year,
                ])
            }}
        </p>

        <ol class="hist-list">
            @foreach ($holidays as $holiday)
                <li class="hist-item" wire:key="ycal-day-{{ $holiday['date']->format('Y-m-d') }}">
                    <span class="hist-when font-mono">{{ $holiday['date']->format('d/m') }}</span>
                    @if ($holiday['kind'] === 'once')
                        <span class="status-tag is-brand">{{ __('catalog.country_holiday.kinds.once') }}</span>
                    @endif
                    <span class="ycal-weekday">{{ __('catalog.country_holiday.calendar.days.'.$holiday['date']->dayOfWeekIso) }}</span>
                    @if ($isBridge($holiday['date']))
                        <span class="status-tag is-warning">{{ __('catalog.country_holiday.calendar.bridge_tag') }}</span>
                    @endif
                    <span class="hist-on">{{ $holiday['name'] }}</span>
                </li>
            @endforeach
        </ol>
    @endif
</div>
