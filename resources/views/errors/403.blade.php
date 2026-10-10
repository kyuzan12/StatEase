@include('errors.layout', [
    'code' => 403,
    'title' => 'Not your survey',
    'message' => 'You do not have access to this page. Surveys and custom scales can only be opened by the account that created them.',
])
