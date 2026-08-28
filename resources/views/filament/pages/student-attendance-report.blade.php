<x-filament-panels::page>
    <div
        x-data
        x-on:download-attendance-reports.window="
            ($event.detail.urls || []).forEach((url, index) => {
                setTimeout(() => {
                    const link = document.createElement('a');
                    link.href = url;
                    link.setAttribute('download', '');
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                }, index * 500);
            })
        "
    >
        <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            এক বা একাধিক ক্লাস ও একটা মাস নির্বাচন করে "Download Reports (PDF)" চাপুন — প্রতিটা নির্বাচিত ক্লাসের জন্য
            সিলেক্ট করা মাসের প্রতিটা দিনের Present (P) / Absent (A) দেখানো আলাদা আলাদা PDF ডাউনলোড হবে।
        </div>

        {{ $this->form }}
    </div>
</x-filament-panels::page>
