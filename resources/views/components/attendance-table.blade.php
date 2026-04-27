@props(['id', 'headers' => []])

<div class="table-responsive">
    <table class="table align-middle table-row-dashed fs-6 gy-5" id="{{ $id }}">
        <thead>
            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                @foreach($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>
