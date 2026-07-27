@if (session('toast_success') || session('toast_warning') || session('toast_error'))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            @if (session('toast_success'))
                window.MuterinToast.success({!! json_encode(session('toast_success')) !!});
            @endif
            @if (session('toast_warning'))
                window.MuterinToast.warning({!! json_encode(session('toast_warning')) !!});
            @endif
            @if (session('toast_error'))
                window.MuterinToast.error({!! json_encode(session('toast_error')) !!});
            @endif
        });
    </script>
@endif
