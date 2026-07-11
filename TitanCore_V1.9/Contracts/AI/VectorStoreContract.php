<?php

namespace Modules\TitanCore\Contracts\AI;

interface VectorStoreContract extends \TitanSDK\Contracts\AI\VectorStoreContract, IndexingContract, RetrievalContract
{
}
