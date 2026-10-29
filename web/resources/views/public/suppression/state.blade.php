{{--
    États de la suppression de compte par le web (noindex) : `sent` (demande reçue, réponse identique que le compte existe
    ou non), `confirm` (bouton POST : un clic automatique sur le lien ne supprime rien), `done` (suppression lancée),
    `invalid` (lien utilisé, expiré ou inconnu, 410). Aucune adresse affichée. Variables : $state, $texts, $token, $seo.
--}}
<x-layouts.public :seo="$seo">
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-9 col-lg-7">
                    <div class="card card-body p-4 p-sm-5" role="{{ $state === 'invalid' ? 'alert' : 'status' }}">
                        <h1 class="h3">{{ $texts['title'] }}</h1>
                        @foreach ((array) $texts['paragraphs'] as $paragraph)
                            <p @class(['text-secondary' => ! $loop->first])>{{ $paragraph }}</p>
                        @endforeach
                        @if ($state === 'confirm')
                            <form method="post" action="{{ route('account-deletion.confirm.store', ['token' => $token]) }}">
                                @csrf
                                <button class="btn btn-danger" type="submit"><i class="bi bi-trash3 me-1" aria-hidden="true"></i>{{ $texts['submit'] }}</button>
                                <a class="btn btn-light ms-2" href="{{ route('home') }}">{{ $texts['cancel'] }}</a>
                            </form>
                        @else
                            <div><a class="btn btn-primary" href="{{ route('home') }}">{{ __('pages/deletion.back_home') }}</a></div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
