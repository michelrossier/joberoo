<x-filament-panels::page>
    <x-filament::section
        heading="Vergleich konfigurieren"
        description="Waehlen Sie einen Job und mindestens zwei Kandidaten fuer den Side-by-Side Vergleich."
    >
        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <label
                    class="mb-2 block text-sm font-medium text-gray-950 dark:text-white"
                    for="compare-campaign-filter"
                >
                    Job
                </label>

                <x-filament::input.wrapper>
                    <x-filament::input.select
                        id="compare-campaign-filter"
                        wire:model.live="campaignId"
                    >
                        <option value="">Bitte waehlen</option>

                        @foreach ($this->campaignOptions as $id => $title)
                            <option value="{{ $id }}">{{ $title }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <div>
                <label
                    class="mb-2 block text-sm font-medium text-gray-950 dark:text-white"
                    for="compare-application-filter"
                >
                    Kandidaten
                </label>

                <x-filament::input.wrapper>
                    <x-filament::input.select
                        id="compare-application-filter"
                        wire:model.live="applicationIds"
                        multiple
                    >
                        @foreach ($this->applicationOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>
    </x-filament::section>

    @php
        $candidates = $this->comparisonCandidates;
        $competencyLabels = $this->competencyLabels;
    @endphp

    @if (! filled($this->campaignId))
        <x-filament::section>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Bitte zuerst einen Job waehlen.
            </p>
        </x-filament::section>
    @elseif (count($candidates) < 2)
        <x-filament::section>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Waehlen Sie mindestens zwei Kandidaten, um den Vergleich zu sehen.
            </p>
        </x-filament::section>
    @else
        <x-filament::section heading="Side-by-Side Vergleich">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Merkmal</th>

                            @foreach ($candidates as $candidate)
                                <th class="px-3 py-2 text-left font-semibold text-gray-900 dark:text-white">
                                    {{ $candidate['name'] }}
                                    <span class="mt-1 block text-xs font-normal text-gray-500 dark:text-gray-400">{{ $candidate['status'] }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr>
                            <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-200">Quelle</td>
                            @foreach ($candidates as $candidate)
                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $candidate['source'] }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-200">Gesamtscore</td>
                            @foreach ($candidates as $candidate)
                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                                    {{ $candidate['overall_score'] !== null ? number_format((float) $candidate['overall_score'], 2) : '-' }}
                                </td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-200">Bewertungen</td>
                            @foreach ($candidates as $candidate)
                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $candidate['evaluation_count'] }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-200">Interviewer-Varianz</td>
                            @foreach ($candidates as $candidate)
                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                                    {{ $candidate['interviewer_variance'] !== null ? number_format((float) $candidate['interviewer_variance'], 2) : '-' }}
                                </td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-200">Eingang</td>
                            @foreach ($candidates as $candidate)
                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $candidate['submitted_at'] ?? '-' }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-200">Letzte Statusaenderung</td>
                            @foreach ($candidates as $candidate)
                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $candidate['last_status_change'] ?? '-' }}</td>
                            @endforeach
                        </tr>

                        @foreach ($competencyLabels as $label)
                            <tr>
                                <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-200">{{ $label }}</td>
                                @foreach ($candidates as $candidate)
                                    @php($score = $candidate['competency_scores'][$label] ?? null)
                                    <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                                        {{ $score !== null ? number_format((float) $score, 2) : '-' }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach

                        <tr>
                            <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-200">Wichtige Notizen</td>
                            @foreach ($candidates as $candidate)
                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                                    @if (count($candidate['key_notes']) === 0)
                                        -
                                    @else
                                        <ul class="list-disc space-y-1 pl-4">
                                            @foreach ($candidate['key_notes'] as $note)
                                                <li>{{ $note->note }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
