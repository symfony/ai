<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini;

enum TaskType: string
{
    /** Unset value, which will default to one of the other enum values. */
    public const TaskTypeUnspecified = 'TASK_TYPE_UNSPECIFIED';
    /** Specifies the given text is a query in a search/retrieval setting. */
    public const RetrievalQuery = 'RETRIEVAL_QUERY';
    /** Specifies the given text is a document from the corpus being searched. */
    public const RetrievalDocument = 'RETRIEVAL_DOCUMENT';
    /** Specifies the given text will be used for STS. */
    public const SemanticSimilarity = 'SEMANTIC_SIMILARITY';
    /** Specifies that the given text will be classified. */
    public const Classification = 'CLASSIFICATION';
    /** Specifies that the embeddings will be used for clustering. */
    public const Clustering = 'CLUSTERING';
    /** Specifies that the given text will be used for question answering. */
    public const QuestionAnswering = 'QUESTION_ANSWERING';
    /** Specifies that the given text will be used for fact verification. */
    public const FactVerification = 'FACT_VERIFICATION';
    /** Specifies that the given text will be used for code retrieval. */
    public const CodeRetrievalQuery = 'CODE_RETRIEVAL_QUERY';
}
