@include('errors.layout', [
    'code' => 429,
    'title' => 'Too many attempts',
    'message' => 'You tried too many times in a short time. Please wait one minute and try again.',
])
