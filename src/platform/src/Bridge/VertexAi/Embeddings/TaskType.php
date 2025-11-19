<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Embeddings;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
enum TaskType: string
{
    /** Used to generate embeddings that are optimized to classify texts according to preset labels. */
    public const CLASSIFICATION = 'CLASSIFICATION';

    /** Used to generate embeddings that are optimized to cluster texts based on their similarities */
    public const CLUSTERING = 'CLUSTERING';

    /** Specifies the given text is a document from the corpus being searched. */
    public const RETRIEVAL_DOCUMENT = 'RETRIEVAL_DOCUMENT';

    /** Specifies the given text is a query in a search/retrieval setting.
     * This is the recommended default for all the embeddings use case
     * that do not align with a documented use case.
     */
    public const RETRIEVAL_QUERY = 'RETRIEVAL_QUERY';

    /** Specifies that the given text will be used for question answering. */
    public const QUESTION_ANSWERING = 'QUESTION_ANSWERING';

    /** Specifies that the given text will be used for fact verification. */
    public const FACT_VERIFICATION = 'FACT_VERIFICATION';

    /** Used to retrieve a code block based on a natural language query,
     * such as sort an array or reverse a linked list.
     * Embeddings of the code blocks are computed using RETRIEVAL_DOCUMENT.
     */
    public const CODE_RETRIEVAL_QUERY = 'CODE_RETRIEVAL_QUERY';

    /** Used to generate embeddings that are optimized to assess text similarity.
     * This is not intended for retrieval use cases.
     */
    public const SEMANTIC_SIMILARITY = 'SEMANTIC_SIMILARITY';
}
