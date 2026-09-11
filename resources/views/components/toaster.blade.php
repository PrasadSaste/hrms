{{--
    Where toasts appear, and what the server has to say this request.

    The messages travel as JSON in a script tag rather than as inline
    JavaScript, so an apostrophe in somebody's name cannot break the page.
    `initToaster()` reads it, shows each one and removes the tag.
--}}
@php
    $toasts = [];

    foreach (['success', 'error', 'warning', 'info'] as $type) {
        if ($message = session($type)) {
            $toasts[] = ['message' => $message, 'type' => $type];
        }
    }

    // What the sign-in and password screens flash.
    if ($status = session('status')) {
        $toasts[] = ['message' => $status, 'type' => 'success'];
    }

    // A list of reasons is not a passing remark: it stays until it is read.
    if (session('skipped') && count(session('skipped'))) {
        $toasts[] = [
            'title' => 'Some employees were skipped',
            'message' => 'The rest were processed.',
            'type' => 'warning',
            'items' => array_values(session('skipped')),
            'persist' => true,
        ];
    }

    /*
     * A form that came back with errors already marks each field. The toast is
     * there for the case the field is off screen — a long form scrolled past
     * the one thing that was wrong looks like a button that did nothing.
     */
    if ($errors->any()) {
        $toasts[] = [
            'message' => $errors->count() === 1
                ? $errors->first()
                : 'That could not be saved: '.$errors->count().' fields need attention.',
            'type' => 'error',
        ];
    }
@endphp

<div data-toaster class="hrms-toaster" role="region" aria-label="Notifications"
    aria-live="polite" aria-relevant="additions text"></div>

@if ($toasts)
    <script type="application/json" data-toast-payload>@json($toasts)</script>
@endif
