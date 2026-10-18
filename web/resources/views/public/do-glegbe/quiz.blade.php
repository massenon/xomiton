{{--
    Quiz vrai / faux de /do-glegbe (MODULE_SPECIFICATIONS.md §A), composant Alpine xmQuiz (resources/js/public/do-glegbe.js,
    chargé à la demande) : verdict immédiat, corrigé ouvert, score quand toutes les affirmations ont une réponse ; rien
    n'est envoyé ni enregistré (aucun <form>, choix autocomplete="off"). Sans JavaScript : choix masqués, corrigé de chaque
    affirmation dans un <details> natif. Les bonnes réponses sont passées au composant (aucun secret : le corrigé est
    dans la page). Annonces dans une seule région polie (verdict + corrigé, puis score). Une affirmation = un <fieldset>
    dont l'énoncé est la <legend> (ACCESSIBILITE.md §4).
    Variables : $quiz (statement, answer, explanation).
--}}
@php
    $total = count($quiz);
    $quizConfig = [
        'answers' => array_map(static fn (array $item): bool => $item['answer'], $quiz),
        'texts' => [
            'correct' => __('pages/do_glegbe.quiz.correct'),
            'wrong' => __('pages/do_glegbe.quiz.wrong'),
            'score' => __('pages/do_glegbe.quiz.score'),
        ],
    ];
@endphp
<section class="card card-body p-4 p-md-5" id="quiz" aria-labelledby="dg-quiz-titre" x-data="xmQuiz(@js($quizConfig))">
    <div class="dg-eyebrow mb-2">{{ __('pages/do_glegbe.quiz.eyebrow') }}</div>
    <h2 class="h4 mb-2" id="dg-quiz-titre">{{ __('pages/do_glegbe.quiz.title') }}</h2>
    <p class="text-secondary mb-4">{{ __('pages/do_glegbe.quiz.intro', ['count' => $total]) }}</p>
    <ol class="list-unstyled vstack gap-3 mb-0">
        @foreach ($quiz as $index => $item)
            <li class="xm-dg-quiz-item" data-quiz-item>
                <fieldset>
                    <legend class="xm-choice-legend fw-semibold" id="dg-quiz-{{ $index }}"><span class="xm-dg-quiz-num" aria-hidden="true">{{ $loop->iteration }}</span><span class="visually-hidden">{{ __('pages/do_glegbe.quiz.statement_label', ['number' => $loop->iteration, 'total' => $total]) }} {{ __('common.separator') }} </span>{{ $item['statement'] }}</legend>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2" data-quiz-choices hidden x-bind:hidden="false">
                        <div class="btn-group">
                            <input type="radio" class="btn-check" name="dg-quiz-{{ $index }}" id="dg-quiz-{{ $index }}-vrai" value="1" autocomplete="off" x-on:change="answer({{ $index }}, true)">
                            <label class="btn btn-sm btn-outline-primary" for="dg-quiz-{{ $index }}-vrai">{{ __('pages/do_glegbe.quiz.true') }}</label>
                            <input type="radio" class="btn-check" name="dg-quiz-{{ $index }}" id="dg-quiz-{{ $index }}-faux" value="0" autocomplete="off" x-on:change="answer({{ $index }}, false)">
                            <label class="btn btn-sm btn-outline-primary" for="dg-quiz-{{ $index }}-faux">{{ __('pages/do_glegbe.quiz.false') }}</label>
                        </div>
                        <span class="small fw-bold text-success" x-show="isRight({{ $index }})" x-cloak><i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i>{{ __('pages/do_glegbe.quiz.correct') }}</span>
                        <span class="small fw-bold xm-dg-quiz-wrong" x-show="isWrong({{ $index }})" x-cloak><i class="bi bi-x-circle-fill me-1" aria-hidden="true"></i>{{ __('pages/do_glegbe.quiz.wrong') }}</span>
                    </div>
                </fieldset>
                <details class="small" data-quiz-details x-bind:open="picks[{{ $index }}] !== null">
                    <summary class="text-primary">{{ __('pages/do_glegbe.quiz.show_answer') }}</summary>
                    <p class="mb-0 mt-1" data-quiz-explanation>{{ $item['explanation'] }}</p>
                </details>
            </li>
        @endforeach
    </ol>
    <p class="visually-hidden" aria-live="polite" data-quiz-live x-text="announce"></p>
    <div class="d-flex flex-wrap align-items-center gap-3 mt-4" data-quiz-score hidden x-bind:hidden="!done()">
        <p class="fw-bold mb-0" x-text="scoreLabel()"></p>
        <button type="button" class="btn btn-sm btn-outline-primary" x-on:click="restart()">{{ __('pages/do_glegbe.quiz.restart') }}</button>
    </div>
</section>
