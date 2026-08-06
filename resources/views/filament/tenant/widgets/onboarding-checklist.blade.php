{{-- @see App\Filament\Tenant\Widgets\OnboardingChecklist --}}
<x-filament-widgets::widget>
    <x-filament::section
        :heading="__('Finish setting up your site')"
        :description="trans_choice('One thing left to do.|:count things left to do.', $this->remainingCount())"
        icon="heroicon-o-clipboard-document-check"
    >
        <ul class="divide-y divide-gray-100 dark:divide-white/10">
            @foreach ($this->steps() as $step)
                <li class="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                    @if ($step['done'])
                        <x-filament::icon
                            icon="heroicon-s-check-circle"
                            class="text-success-600 dark:text-success-400 mt-0.5 h-5 w-5 shrink-0"
                        />
                    @else
                        <span
                            class="mt-0.5 h-5 w-5 shrink-0 rounded-full border-2 border-gray-300 dark:border-gray-600"
                            aria-hidden="true"
                        ></span>
                    @endif

                    <div class="min-w-0 flex-1">
                        @if ($step['done'])
                            <p class="text-sm text-gray-400 line-through dark:text-gray-500">
                                {{ $step['task']->label() }}
                            </p>
                        @else
                            {{-- The reason is only shown while the task is
                                 outstanding: it names the consequence of leaving
                                 it, which reads as nagging once it is done. --}}
                            <a
                                href="{{ $step['url'] }}"
                                class="hover:text-primary-600 dark:hover:text-primary-400 text-sm font-semibold text-gray-950 dark:text-white"
                            >
                                {{ $step['task']->label() }}
                            </a>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ $step['task']->why() }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
