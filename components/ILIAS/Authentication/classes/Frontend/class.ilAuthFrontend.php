<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

use ILIAS\Authentication\Login\Policy\PostLoginPolicyRunner;
use ILIAS\Authentication\Login\CompleteSuccessfulLogin;
use ILIAS\Authentication\Login\LoginSubjectFactory;
use ILIAS\Authentication\Login\FailedLoginCandidateResolver;
use ILIAS\Authentication\Login\Adapter\SecuritySettingsAdapter;
use ILIAS\Authentication\Login\Adapter\UserAdapter;
use ILIAS\Authentication\Login\Port\Lockout\RecordFailedLoginAttempts;
use ILIAS\Authentication\Login\UserId;
use ILIAS\User\Profile\Profile;

class ilAuthFrontend implements ilAuthFrontendInterface
{
    public const string MIG_EXTERNAL_ACCOUNT = 'mig_ext_account';
    public const string MIG_TRIGGER_AUTHMODE = 'mig_trigger_auth_mode';
    public const string MIG_DESIRED_AUTHMODE = 'mig_desired_auth_mode';

    private ilLogger $logger;
    private ilAuthCredentials $credentials;
    private ilAuthStatus $status;
    /** @var list<ilAuthProviderInterface> */
    private array $providers;
    private ilAuthSession $auth_session;
    private ilAppEventHandler $ilAppEventHandler;
    private Profile $user_profile;
    private LoginSubjectFactory $login_subject_factory;
    private PostLoginPolicyRunner $post_login_policy_runner;
    private RecordFailedLoginAttempts $record_failed_login_attempts;
    private CompleteSuccessfulLogin $complete_successful_login;
    private FailedLoginCandidateResolver $failed_login_candidate_resolver;

    /**
     * @param list<ilAuthProviderInterface> $providers
     */
    public function __construct(
        ilAuthSession $session,
        ilAuthStatus $status,
        ilAuthCredentials $credentials,
        array $providers,
        SecuritySettingsAdapter $security_adapter,
        PostLoginPolicyRunner $post_login_policy_runner
    )
    {
        global $DIC;
        $this->logger = $DIC->logger()->auth();
        $this->ilAppEventHandler = $DIC->event();

        $this->auth_session = $session;
        $this->credentials = $credentials;
        $this->status = $status;
        $this->providers = $providers;
        $this->post_login_policy_runner = $post_login_policy_runner;

        $this->user_profile = $DIC['user']->getProfile();

        $this->login_subject_factory = new LoginSubjectFactory($DIC->database());

        $user_adapter = new UserAdapter($DIC->database());

        $this->record_failed_login_attempts = new RecordFailedLoginAttempts(
            $security_adapter,  // LoginAttemptLimit
            $user_adapter,      // LoginAttemptRepository
            $user_adapter       // AccountDeactivation
        );

        $this->complete_successful_login = new CompleteSuccessfulLogin(
            $user_adapter,      // LoginAttemptRepository
            $user_adapter,      // LoginTimestampsRepository
            $user_adapter,      // PasswordChangeTrackingRepository
            $security_adapter   // PasswordChangeOnFirstLoginSettings
        );

        $this->failed_login_candidate_resolver = new FailedLoginCandidateResolver($DIC->database());
    }

    public function getAuthSession(): ilAuthSession
    {
        return $this->auth_session;
    }

    public function getCredentials(): ilAuthCredentials
    {
        return $this->credentials;
    }

    /**
     * @return list<ilAuthProviderInterface>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    public function getStatus(): ilAuthStatus
    {
        return $this->status;
    }

    public function resetStatus(): void
    {
        $this->getStatus()->setStatus(ilAuthStatus::STATUS_UNDEFINED);
        $this->getStatus()->setReason('');
        $this->getStatus()->setAuthenticatedUserId(ANONYMOUS_USER_ID);
    }

    public function migrateAccount(ilAuthSession $session): bool
    {
        if (!$session->isAuthenticated()) {
            $this->logger->warning('Desired user account is not authenticated');
            return false;
        }
        $user = ilObjectFactory::getInstanceByObjId($session->getUserId(), false);

        if (!$user instanceof ilObjUser) {
            $this->logger->info('Cannot instantiate user account for account migration: ' . $session->getUserId());
            return false;
        }

        $user->setAuthMode(ilSession::get(static::MIG_DESIRED_AUTHMODE));

        $this->logger->debug('new auth mode is: ' . ilSession::get(self::MIG_DESIRED_AUTHMODE));

        $user->setExternalAccount(ilSession::get(static::MIG_EXTERNAL_ACCOUNT));
        $user->update();

        foreach ($this->getProviders() as $provider) {
            if (!$provider instanceof ilAuthProviderAccountMigrationInterface) {
                $this->logger->warning('Provider: ' . get_class($provider) . ' does not support account migration.');
                throw new InvalidArgumentException('Invalid auth provider given.');
            }
            $this->getCredentials()->setUsername(ilSession::get(static::MIG_EXTERNAL_ACCOUNT));
            $provider->migrateAccount($this->getStatus());
            if ($this->getStatus()->getStatus() === ilAuthStatus::STATUS_AUTHENTICATED) {
                return $this->handleAuthenticationSuccess($provider);
            }
        }
        return $this->handleAuthenticationFail();
    }

    public function migrateAccountNew(): bool
    {
        foreach ($this->providers as $provider) {
            if (!$provider instanceof ilAuthProviderAccountMigrationInterface) {
                $this->logger->warning('Provider: ' . get_class($provider) . ' does not support account migration.');
                throw new InvalidArgumentException('Invalid auth provider given.');
            }
            $provider->createNewAccount($this->getStatus());

            if ($provider instanceof ilAuthProviderInterface &&
                $this->getStatus()->getStatus() === ilAuthStatus::STATUS_AUTHENTICATED) {
                return $this->handleAuthenticationSuccess($provider);
            }
        }
        return $this->handleAuthenticationFail();
    }


    public function authenticate(): bool
    {
        foreach ($this->getProviders() as $provider) {
            $this->resetStatus();

            $this->logger->debug('Trying authentication against: ' . get_class($provider));

            $provider->doAuthentication($this->getStatus());

            $this->logger->debug('Authentication user id: ' . $this->getStatus()->getAuthenticatedUserId());

            switch ($this->getStatus()->getStatus()) {
                case ilAuthStatus::STATUS_AUTHENTICATED:
                    return $this->handleAuthenticationSuccess($provider);

                case ilAuthStatus::STATUS_ACCOUNT_MIGRATION_REQUIRED:
                    $this->logger->notice('Account migration required.');
                    if ($provider instanceof ilAuthProviderAccountMigrationInterface) {
                        return $this->handleAccountMigration($provider);
                    }

                    $this->logger->error('Authentication migratittion required but provider does not support interface' . get_class($provider));
                    break;
                case ilAuthStatus::STATUS_AUTHENTICATION_FAILED:
                default:
                    $this->logger->debug('Authentication failed against: ' . get_class($provider));
                    break;
            }
        }
        return $this->handleAuthenticationFail();
    }

    protected function handleAccountMigration(ilAuthProviderAccountMigrationInterface $provider): bool
    {
        $this->logger->debug('Trigger auth mode: ' . $provider->getTriggerAuthMode());
        $this->logger->debug('Desired auth mode: ' . $provider->getUserAuthModeName());
        $this->logger->debug('External account: ' . $provider->getExternalAccountName());

        $this->getStatus()->setAuthenticatedUserId(ANONYMOUS_USER_ID);
        #$this->getStatus()->setStatus(ilAuthStatus::STATUS_AUTHENTICATED);

        ilSession::set(static::MIG_TRIGGER_AUTHMODE, $provider->getTriggerAuthMode());
        ilSession::set(static::MIG_DESIRED_AUTHMODE, $provider->getUserAuthModeName());
        ilSession::set(static::MIG_EXTERNAL_ACCOUNT, $provider->getExternalAccountName());

        $this->logger->dump(ilSession::dumpToString(), ilLogLevel::DEBUG);

        return true;
    }

    protected function handleAuthenticationSuccess(ilAuthProviderInterface $provider): bool
    {
        // reset expired status
        $this->getAuthSession()->setExpired(false);

        $user_id = new UserId($this->getStatus()->getAuthenticatedUserId());
        $subject = $this->login_subject_factory->forUserId($user_id);

        $policy_result = $this->post_login_policy_runner->evaluate($subject);

        if ($policy_result->isError()) {
            if ($policy_result->error() === 'STATUS_CODE_ACTIVATION_REQUIRED') {
                $this->logger->debug('Account reactivation codes are active');
                $this->getStatus()->setStatus(ilAuthStatus::STATUS_CODE_ACTIVATION_REQUIRED);
            } else {
                $this->logger->debug('Account reactivation codes are inactive');
                $this->getStatus()->setStatus(ilAuthStatus::STATUS_AUTHENTICATION_FAILED);
                $this->getStatus()->setReason($policy_result->error());
                $this->getStatus()->setAuthenticatedUserId(ANONYMOUS_USER_ID);
            }
            return false;
        }

        $user = $this->instantiateUser($user_id->value());
        if (!$user instanceof ilObjUser || $user->getId() === ANONYMOUS_USER_ID) {
            return false;
        }

        // check if profile is complete
        if ($this->user_profile->isProfileIncomplete($user)
            && ilAuthFactory::getContext() !== ilAuthFactory::CONTEXT_ECS
            && ilContext::getType() !== ilContext::CONTEXT_LTI_PROVIDER) {
            ilLoggerFactory::getLogger('auth')->info('User profile is incomplete.');
            $user->setProfileIncomplete(true);
        }

        // redirects in case of error (session pool limit reached)
        ilSessionControl::handleLoginEvent($user->getLogin(), $this->getAuthSession());

        // @todo move to event handling
        ilOnlineTracking::addUser($user->getId());

        $this->complete_successful_login->execute($subject);

        $this->logger->info('Successfully authenticated: ' . ilObjUser::_lookupLogin($this->getStatus()->getAuthenticatedUserId()));
        $this->getAuthSession()->setAuthenticated(true, $this->getStatus()->getAuthenticatedUserId());

        ilInitialisation::initUserAccount();

        ilSession::set('orig_request_target', '');

        // --- anonymous/registered user
        if (PHP_SAPI !== 'cli') {
            $this->logger->info(
                'logged in as ' . $user->getLogin() .
                ', remote:' . $_SERVER['REMOTE_ADDR'] . ':' . $_SERVER['REMOTE_PORT'] .
                ', server:' . $_SERVER['SERVER_ADDR'] . ':' . $_SERVER['SERVER_PORT']
            );
        } else {
            $this->logger->info(
                'logged in as ' . $user->getLogin() . ' from CLI'
            );
        }

        // finally raise event
        $this->ilAppEventHandler->raise(
            'components/ILIAS/Authentication',
            'afterLogin',
            [
                'username' => $user->getLogin()
            ]
        );

        $this->getStatus()->setReason('');

        return true;
    }

    protected function handleAuthenticationFail(): bool
    {
        $this->logger->debug('Authentication failed for all authentication methods.');

        $this->handleLoginAttempts();

        return false;
    }

    protected function handleLoginAttempts(): void
    {
        $candidate_usr_ids = $this->failed_login_candidate_resolver->resolve($this->getCredentials());

        $result = $this->record_failed_login_attempts->execute($candidate_usr_ids);
        if ($result->value() > 0) {
            $this->getStatus()->setReason('auth_err_invalid_user_account');
        }
    }

    private function instantiateUser(int $user_id): ?ilObjUser
    {
        $user = ilObjectFactory::getInstanceByObjId($user_id, false);

        if (!$user instanceof ilObjUser) {
            $this->logger->error('Cannot instantiate user account with id: ' . $user_id);
            $this->getStatus()->setStatus(ilAuthStatus::STATUS_AUTHENTICATION_FAILED);
            $this->getStatus()->setAuthenticatedUserId(ANONYMOUS_USER_ID);
            return null;
        }

        return $user;
    }
}
