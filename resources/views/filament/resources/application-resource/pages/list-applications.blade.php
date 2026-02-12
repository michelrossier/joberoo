<x-filament-panels::page>
    <div class="flex flex-nowrap gap-4 overflow-x-auto pb-2">
        @foreach ($this->lanes as $lane)
            <section class="flex h-[calc(100vh-14rem)] min-w-[18rem] max-w-[18rem] flex-col rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <header class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $lane['label'] }}</h3>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                        {{ count($lane['applications']) }}
                    </span>
                </header>

                <div
                    class="min-h-[12rem] flex-1 space-y-3 overflow-y-auto bg-gray-50/60 p-3 dark:bg-gray-950/30"
                    x-on:dragover.prevent
                    x-on:drop.prevent="
                        const id = Number(event.dataTransfer.getData('application-id'));

                        if (! Number.isNaN(id)) {
                            $wire.moveApplication(id, '{{ $lane['value'] }}');
                        }
                    "
                >
                    @forelse ($lane['applications'] as $application)
                        <article
                            wire:key="application-card-{{ $application->id }}"
                            draggable="true"
                            class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm transition hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-600"
                            x-on:dragstart="event.dataTransfer.setData('application-id', '{{ $application->id }}')"
                        >
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $application->full_name }}</p>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ $application->campaign?->title ?? '-' }}</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Zustaendig: {{ $application->assignedUser?->name ?? 'Nicht zugewiesen' }}
                            </p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Eingegangen am {{ $application->created_at?->format('d.m.Y H:i') }}
                            </p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Quelle: {{ $application->source_label }}
                            </p>

                            <a
                                class="mt-3 inline-flex text-xs font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300"
                                href="{{ \App\Filament\Resources\ApplicationResource::getUrl('view', ['record' => $application]) }}"
                            >
                                Bewerbung anzeigen
                            </a>
                        </article>
                    @empty
                        <p class="rounded-lg border border-dashed border-gray-200 bg-white px-3 py-4 text-center text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                            Keine Bewerbungen
                        </p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
