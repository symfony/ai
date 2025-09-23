<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\AiMlApi;

use Symfony\AI\Platform\Model;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de
 */
class Embeddings extends Model
{
    public const TEXT_EMBEDDING_3_SMALL = 'text-embedding-3-small';
    public const TEXT_EMBEDDING_3_LARGE = 'text-embedding-3-large';
    public const TEXT_EMBEDDING_ADA_002 = 'text-embedding-ada-002';
    public const TOGETHERCOMPUTER_M2_BERT_80M_32K_RETRIEVAL = 'togethercomputer/m2-bert-80M-32k-retrieval';
    public const BAAI_BGE_BASE_EN_V1_5 = 'BAAI/bge-base-en-v1.5';
    public const BAAI_BGE_LARGE_EN_V1 = 'BAAI/bge-large-en-v1.';
    public const VOYAGE_LARGE_2_INSTRUCT = 'voyage-large-2-instruct';
    public const VOYAGE_FINANCE_2 = 'voyage-finance-2';
    public const VOYAGE_MULTILINGUAL_2 = 'voyage-multilingual-2';
    public const VOYAGE_LAW_2 = 'voyage-law-2';
    public const VOYAGE_CODE_2 = 'voyage-code-2';
    public const VOYAGE_LARGE_2 = 'voyage-large-2';
    public const VOYAGE_2 = 'voyage-2';
    public const TEXTEMBEDDING_GECKO_003 = 'textembedding-gecko@003';
    public const TEXTEMBEDDING_GECKO_MULTILINGUAL_001 = 'textembedding-gecko-multilingual@001';
    public const TEXT_MULTILINGUAL_EMBEDDING_002 = 'text-multilingual-embedding-002';
}
