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

use ILIAS\Authentication\Login\Adapter\AccountReactivationAdapter;
use ILIAS\Authentication\Login\Adapter\ActiveSessionProbeAdapter;
use ILIAS\Authentication\Login\Adapter\RequestClientIpAdapter;
use ILIAS\Authentication\Login\Adapter\SecuritySettingsAdapter;
use ILIAS\Authentication\Login\Adapter\SimultaneousLoginSettingsAdapter;
use ILIAS\Authentication\Login\ClientIpMatcher;
use ILIAS\Authentication\Login\Policy\ActiveAccountPolicy;
use ILIAS\Authentication\Login\Policy\ClientIpPolicy;
use ILIAS\Authentication\Login\Policy\InactiveAccountLoginAttemptPolicy;
use ILIAS\Authentication\Login\Policy\PostLoginPolicyRunner;
use ILIAS\Authentication\Login\Policy\SimultaneousLoginPolicy;
use ILIAS\Authentication\Login\Policy\TimeLimitPolicy;
use ILIAS\Data\Factory as DataFactory;

class ilAuthFrontendFactory
{
    private const int CONTEXT_UNDEFINED = 0;
    /**
     * Authentication with id and password. Used for standard form based authentication,
     * SOAP auth (login), but not for (CLI (cron)) and HTTP basic authentication
     */
    public const int CONTEXT_STANDARD_FORM = 2;
    public const int CONTEXT_CLI = 3;
    /** @var int Rest soap context */
    public const int CONTEXT_WS = 4;
    public const int CONTEXT_HTTP = 5;

    private int $context = self::CONTEXT_UNDEFINED;
    private ilSetting $settings;
    private ilLogger $logger;
    private SecuritySettingsAdapter $security_adapter;

    public function __construct()
    {
        global $DIC;
        $this->logger = $DIC->logger()->auth();
        $this->settings = $DIC->settings();
        $this->security_adapter = new SecuritySettingsAdapter(ilSecuritySettings::_getInstance());
    }

    public function setContext(int $a_context): void
    {
        $this->context = $a_context;
    }

    public function getContext(): int
    {
        return $this->context;
    }

    /**
     * @param list<ilAuthProviderInterface> $providers
     */
    public function getFrontend(
        ilAuthSession $session,
        ilAuthStatus $status,
        ilAuthCredentials $credentials,
        array $providers
    ): ?ilAuthFrontendInterface
    {
        switch ($this->getContext()) {
            case self::CONTEXT_CLI:
                return $this->getAuthFrontendCLI($session, $status, $credentials, $providers);
            case self::CONTEXT_WS:
                return $this->getAuthFrontendWS($session, $status, $credentials, $providers);
            case self::CONTEXT_STANDARD_FORM:
                return $this->getAuthStandardFormFrontend($session, $status, $credentials, $providers);
            case self::CONTEXT_HTTP:
                return $this->getAuthFrontendHTTP($session, $status, $credentials, $providers);
            case self::CONTEXT_UNDEFINED:
                $this->logger->error('Trying to init auth with empty context');
                break;
        }

        return null;
    }

    /**
     * @param ilAuthSession     $session
     * @param ilAuthStatus      $status
     * @param ilAuthCredentials $credentials
     * @param array             $providers
     * @return ilAuthFrontendCLI
     */
    private function getAuthFrontendCLI(
        ilAuthSession $session,
        ilAuthStatus $status,
        ilAuthCredentials $credentials,
        array $providers
    ): ilAuthFrontendCLI
    {
        $this->logger->debug('Init auth frontend with standard auth context');

        $post_login_policies = [
            new InactiveAccountLoginAttemptPolicy($this->security_adapter),
            new ActiveAccountPolicy(),
            new TimeLimitPolicy(
                new AccountReactivationAdapter($this->settings),
                (new DataFactory())->clock()->utc()
            ),
            // no IP Policy, since CLI does not set $_SERVER['REMOTE_ADDR']
            new SimultaneousLoginPolicy(
                new SimultaneousLoginSettingsAdapter($this->settings),
                new ActiveSessionProbeAdapter()
            ),
        ];

        $post_login_policy_runner = new PostLoginPolicyRunner($post_login_policies);

        return new ilAuthFrontendCLI(
            $session,
            $status,
            $credentials,
            $providers,
            $this->security_adapter,
            $post_login_policy_runner
        );
    }

    /**
     * @param ilAuthSession     $session
     * @param ilAuthStatus      $status
     * @param ilAuthCredentials $credentials
     * @param array             $providers
     * @return ilAuthFrontendWS
     */
    private function getAuthFrontendWS(
        ilAuthSession $session,
        ilAuthStatus $status,
        ilAuthCredentials $credentials,
        array $providers
    ): ilAuthFrontendWS
    {
        $this->logger->debug('Init auth frontend with webservice auth context');

        $post_login_policies = [
            new InactiveAccountLoginAttemptPolicy($this->security_adapter),
            new ActiveAccountPolicy(),
            new TimeLimitPolicy(
                new AccountReactivationAdapter($this->settings),
                (new DataFactory())->clock()->utc()
            ),
            new ClientIpPolicy(
                new RequestClientIpAdapter(),
                new ClientIpMatcher()
            ),
            new SimultaneousLoginPolicy(
                new SimultaneousLoginSettingsAdapter($this->settings),
                new ActiveSessionProbeAdapter()
            ),
        ];

        $post_login_policy_runner = new PostLoginPolicyRunner($post_login_policies);

        return new ilAuthFrontendWS(
            $session,
            $status,
            $credentials,
            $providers,
            $this->security_adapter,
            $post_login_policy_runner
        );
    }

    /**
     * @param ilAuthSession     $session
     * @param ilAuthStatus      $status
     * @param ilAuthCredentials $credentials
     * @param array             $providers
     * @return ilAuthStandardFormFrontend
     */
    private function getAuthStandardFormFrontend(
        ilAuthSession $session,
        ilAuthStatus $status,
        ilAuthCredentials $credentials,
        array $providers
    ): ilAuthStandardFormFrontend
    {
        $this->logger->debug('Init auth frontend with standard auth context');

        $post_login_policies = [
            new InactiveAccountLoginAttemptPolicy($this->security_adapter),
            new ActiveAccountPolicy(),
            new TimeLimitPolicy(
                new AccountReactivationAdapter($this->settings),
                (new DataFactory())->clock()->utc()
            ),
            new ClientIpPolicy(
                new RequestClientIpAdapter(),
                new ClientIpMatcher()
            ),
            new SimultaneousLoginPolicy(
                new SimultaneousLoginSettingsAdapter($this->settings),
                new ActiveSessionProbeAdapter()
            ),
        ];

        $post_login_policy_runner = new PostLoginPolicyRunner($post_login_policies);

        return new ilAuthStandardFormFrontend(
            $session,
            $status,
            $credentials,
            $providers,
            $this->security_adapter,
            $post_login_policy_runner
        );
    }

    /**
     * @param ilAuthSession     $session
     * @param ilAuthStatus      $status
     * @param ilAuthCredentials $credentials
     * @param array             $providers
     * @return ilAuthFrontendHTTP
     */
    private function getAuthFrontendHTTP(
        ilAuthSession $session,
        ilAuthStatus $status,
        ilAuthCredentials $credentials,
        array $providers
    ): ilAuthFrontendHTTP
    {
        $this->logger->debug('Init auth frontend with http basic auth context');

        $post_login_policies = [
            new InactiveAccountLoginAttemptPolicy($this->security_adapter),
            new ActiveAccountPolicy(),
            new TimeLimitPolicy(
                new AccountReactivationAdapter($this->settings),
                (new DataFactory())->clock()->utc()
            ),
            new ClientIpPolicy(
                new RequestClientIpAdapter(),
                new ClientIpMatcher()
            ),
            new SimultaneousLoginPolicy(
                new SimultaneousLoginSettingsAdapter($this->settings),
                new ActiveSessionProbeAdapter()
            ),
        ];

        $post_login_policy_runner = new PostLoginPolicyRunner($post_login_policies);

        return new ilAuthFrontendHTTP(
            $session,
            $status,
            $credentials,
            $providers,
            $this->security_adapter,
            $post_login_policy_runner
        );
    }
}
