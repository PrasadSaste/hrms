<div class="grid gap-6 lg:grid-cols-3">
    <x-card title="Announcement" class="lg:col-span-2">
        <div class="space-y-4">
            <x-input label="Title" name="title" :value="$announcement->title" required />
            <x-rich-text label="Body" name="body" :value="$announcement->body" :rows="12" required hard-breaks
                help="Formatting, lists, tables and links. Every line break you type is kept." />
        </div>
    </x-card>

    <div class="space-y-6">
        <x-card title="Audience">
            <div class="space-y-4">
                <x-select label="Branch" name="branch_id" :selected="$announcement->branch_id" placeholder="All branches"
                    :options="$branches->pluck('name', 'id')->all()" />
                <x-select label="Department" name="department_id" :selected="$announcement->department_id"
                    placeholder="All departments" :options="$departments->pluck('name', 'id')->all()"
                    help="Leave both blank to reach everyone." />
            </div>
        </x-card>

        <x-card title="Publishing">
            <div class="space-y-4">
                <x-select label="Status" name="status" :selected="$announcement->status ?? 'draft'" required
                    :options="['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived']" />
                <x-input label="Publish at" name="published_at" type="datetime-local"
                    :value="$announcement->published_at?->format('Y-m-d\TH:i')"
                    help="Defaults to now when you publish." />
                <x-input label="Expires at" name="expires_at" type="datetime-local"
                    :value="$announcement->expires_at?->format('Y-m-d\TH:i')"
                    help="Optional. The notice hides itself after this time." />
                <x-checkbox label="Pin to the top" name="is_pinned" :checked="$announcement->is_pinned ?? false" />
                <x-checkbox label="Also send by email" name="notify_by_email" :checked="$announcement->notify_by_email ?? false"
                    help="Sends to everyone in the selected audience when it is first published." />
            </div>
        </x-card>

    </div>
</div>

<x-form-actions>
    <x-button size="lg">{{ $announcement->exists ? 'Save changes' : 'Create announcement' }}</x-button>
    <x-button href="{{ route('announcements.index') }}" variant="secondary" size="lg">Cancel</x-button>
</x-form-actions>
