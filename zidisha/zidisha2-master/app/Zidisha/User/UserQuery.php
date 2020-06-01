<?php

namespace Zidisha\User;

use Faker\Provider\DateTime;
use Zidisha\User\Base\UserQuery as BaseUserQuery;


/**
 * Skeleton subclass for performing query and update operations on the 'users' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class UserQuery extends BaseUserQuery
{

    public function filterAbandoned(\DateTime $dateTime)
    {
        return $this->filterByActive(true)->filterByLastLoginAt(['max' => $dateTime]);
    }
} // UserQuery