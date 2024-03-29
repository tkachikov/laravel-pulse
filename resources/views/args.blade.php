@foreach(['arguments' => '', 'options' => '--'] as $key => $prefix)
    @foreach($command->getDefinition()->{'get'.ucfirst($key)}() as $input)
        @php
            $name = $prefix.$input->getName();
        @endphp
        <div class="row w-100 mx-auto align-items-center">
            <div class="col">{{ $name }}</div>
            <div class="col">
                @if($key === 'arguments' || ($key === 'options' && $input->acceptValue()))
                    <input type="text" name="args[{{ $name }}]" class="form-control" value="{{ $schedule?->args[$name] ?? $input->getDefault() }}" form="{{ $form }}">
                @else
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="args[{{ $name }}]" form="{{ $form }}">
                    </div>
                @endif
            </div>
        </div>
    @endforeach
@endforeach
