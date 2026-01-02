<div aria-live="assertive" class="fixed inset-0 flex items-end px-4 py-6 pointer-events-none sm:p-6 sm:items-start z-50">
    <div class="w-full flex flex-col items-center sm:items-end space-y-4">
        @if (session()->has('success'))
            <x-notification type="success" :message="session('success')" />
        @endif

        @if (session()->has('error'))
            <x-notification type="error" :message="session('error')" />
        @endif

        @if (session()->has('status'))
            <x-notification type="info" :message="session('status')" />
        @endif
        
        @if (session()->has('warning'))
            <x-notification type="warning" :message="session('warning')" />
        @endif
    </div>
</div>
