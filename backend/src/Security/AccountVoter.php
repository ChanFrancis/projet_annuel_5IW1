<?php

namespace App\Security;

use App\Entity\Account;
use App\Entity\User;
use App\Repository\AccountUserRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Authorises actions on an Account based on the user's membership role.
 *
 *  - VIEW   : any member (owner / co_owner / viewer)
 *  - EDIT   : owner or co_owner (add/edit transactions)
 *  - MANAGE : owner only (rename/delete account, manage members & invitations)
 *
 * @extends Voter<string, Account>
 */
class AccountVoter extends Voter
{
    public const VIEW = 'ACCOUNT_VIEW';
    public const EDIT = 'ACCOUNT_EDIT';
    public const MANAGE = 'ACCOUNT_MANAGE';

    public function __construct(private readonly AccountUserRepository $memberships)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::EDIT, self::MANAGE], true)
            && $subject instanceof Account;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Account $subject */
        $membership = $this->memberships->findMembership($subject, $user);
        if (null === $membership) {
            return false;
        }

        $role = $membership->getRole();

        return match ($attribute) {
            self::VIEW => true,
            self::EDIT => $role->canWrite(),
            self::MANAGE => $role->canManage(),
            default => false,
        };
    }
}
