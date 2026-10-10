@include('errors.layout', [
    'code' => 500,
    'title' => 'Something went wrong',
    'message' => 'StatEase ran into an unexpected problem. Please try again; if it keeps happening, check that MySQL is running.',
])
