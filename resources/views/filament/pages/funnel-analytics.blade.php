<x-filament-panels::page>
    <x-filament::section
        heading="Filter"
        description="Waehlen Sie Job und Zeitraum fuer die Analyse."
    >
        <x-filament::grid
            :default="1"
            :md="2"
        >
            <x-filament::grid.column>
                <label
                    class="mb-2 block text-sm font-medium text-gray-950 dark:text-white"
                    for="campaign-filter"
                >
                    Job
                </label>

                <x-filament::input.wrapper>
                    <x-filament::input.select
                        id="campaign-filter"
                        wire:model.live="campaignId"
                    >
                        <option value="">Alle Jobs</option>

                        @foreach ($this->campaignOptions as $id => $title)
                            <option value="{{ $id }}">{{ $title }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </x-filament::grid.column>

            <x-filament::grid.column>
                <label
                    class="mb-2 block text-sm font-medium text-gray-950 dark:text-white"
                    for="days-filter"
                >
                    Zeitraum
                </label>

                <x-filament::input.wrapper>
                    <x-filament::input.select
                        id="days-filter"
                        wire:model.live="days"
                    >
                        <option value="7">Letzte 7 Tage</option>
                        <option value="30">Letzte 30 Tage</option>
                        <option value="90">Letzte 90 Tage</option>
                        <option value="365">Letzte 365 Tage</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </x-filament::grid.column>
        </x-filament::grid>
    </x-filament::section>
</x-filament-panels::page>

