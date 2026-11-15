CHANGELOG
=========

1.0
---

 * Add `Contract\InputNormalizer` and `Contract\QuestionNormalizer`, normalizing the input and the questions of a decision into the request body of TypeSafe, Ollama and OpenRouter, for platforms to register in their contract
 * Add getters to the questions
 * [BC BREAK] Move `Question\*` and `Answer\*` to the Platform component, in the `Symfony\AI\Platform\Decision` namespace
 * [BC BREAK] Remove `Evaluation`, pass the state as the input of `invoke()` and the questions as its `questions` option
 * [BC BREAK] The questions don't implement `JsonSerializable` anymore, the contract of each platform normalizes them
 * [BC BREAK] Remove `RequestBodyBuilder`, the contract normalizes the input and the questions before the model client sends them
 * [BC BREAK] Remove the `fromArray()` methods of `NoulAnswer`, `ChoiceAnswer` and `ScoreAnswer`
 * [BC BREAK] Restrict the instructions of questions to strings
 * [BC BREAK] Require at least two options for `ChoiceQuestion`
 * Add images as inputs of a decision, in a `Collection` with the text of the state

0.15
----

 * Add the bridge, holding the `Evaluation`, `Question\*`, `Answer\*`, `AnswersConverter` and `TokenUsageExtractor` classes shared by providers of decision models
