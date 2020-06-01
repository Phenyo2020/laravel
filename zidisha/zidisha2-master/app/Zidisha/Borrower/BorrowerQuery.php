<?php

namespace Zidisha\Borrower;

use Zidisha\Borrower\Base\BorrowerQuery as BaseBorrowerQuery;


/**
 * Skeleton subclass for performing query and update operations on the 'borrowers' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class BorrowerQuery extends BaseBorrowerQuery
{

    public function filterPendingActivation()
    {
        return $this->filterByActivationStatus(Borrower::ACTIVATION_PENDING);
    }
    
} // BorrowerQuery
