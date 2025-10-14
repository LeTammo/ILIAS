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

namespace ILIAS\DualOptIn\Service;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use ilAccountRegistrationMail;
use ILIAS\Data\Clock\ClockFactoryImpl;
use ILIAS\Data\Factory as DataFactory;
use ILIAS\DI\Container;
use ILIAS\DualOptIn\Repository\RegistrationHashRepositoryImpl;
use ILIAS\User\Settings\NewAccountMail\Repository as NewAccountMailRepository;
use ilLoggerFactory;
use ilObjectFactory;
use ilObjUser;
use ilRegConfirmationLinkExpiredException;
use ilRegistrationHashNotFoundException;
use ilRegistrationMimeMailNotification;
use ilRegistrationSettings;
use ilSecuritySettingsChecker;
use ilSoapClient;

class DualOptInServiceImpl implements DualOptInService
{
    public const string ID = "reg_hash_service";
    protected readonly Container $dic;
    protected readonly RegistrationHashRepositoryImpl $reg_hash_repository;

    public function __construct(Container $dic)
    {
        $this->dic = $dic;
        $this->reg_hash_repository = new RegistrationHashRepositoryImpl($this->dic->database(), new ClockFactoryImpl());
    }

    /**
     * @throws ilRegistrationHashNotFoundException
     * @throws ilRegConfirmationLinkExpiredException
     */
    public function verifyAndActivateUser(string $hash): ilObjUser
    {
        $user_id = $this->verifyHash($hash);

        /** @var ilObjUser $user */
        $user = ilObjectFactory::getInstanceByObjId($user_id);

        $this->activateUser($user);

        return $user;
    }

    /**
     * @throws ilRegistrationHashNotFoundException
     * @throws ilRegConfirmationLinkExpiredException
     */
    private function verifyHash(string $hash): int
    {
        $reg_hash = $this->reg_hash_repository->findByHash($hash);
        if (!$reg_hash) {
            throw new ilRegistrationHashNotFoundException('reg_confirmation_hash_not_found');
        }

        $lifetime = (new ilRegistrationSettings())->getRegistrationHashLifetime();
        if ($lifetime > 0) {
            $interval = new DateInterval("PT{$lifetime}S");
            $cutoff = (new DataFactory())->clock()->utc()->now()->sub($interval);
            $created = (new DataFactory())->clock()->utc()->now()->setTimestamp($reg_hash->getCreationDate());

            if ($created < $cutoff) {
                $this->triggerExpiredUserCleanup($reg_hash->getUserId());

                throw new ilRegConfirmationLinkExpiredException(
                    'reg_confirmation_hash_life_time_expired',
                    $reg_hash->getUserId()
                );
            }
        }

        $this->reg_hash_repository->deleteByUserId($reg_hash->getUserId());

        return $reg_hash->getUserId();
    }

    public function distributeMailsOnRegistration(ilObjUser $user, ilRegistrationSettings $settings): void
    {
        $hash = $this->reg_hash_repository->create($user->getId());

        $mail = new ilRegistrationMimeMailNotification($user, $hash, $settings->getRegistrationHashLifetime());
        $mail->setType(ilRegistrationMimeMailNotification::TYPE_NOTIFICATION_ACTIVATION);
        $mail->setRecipients([$user]);
        $mail->send();
    }

    public function deleteExpiredUserObjects(int $usr_id): void
    {
        $logger = $this->dic->logger()->user();

        $logger->debug(
            'Started deletion of inactive user objects with expired confirmation hash values (dual opt in) ...'
        );
        $lifetime = (new ilRegistrationSettings())->getRegistrationHashLifetime();

        if ($lifetime <= 0) {
            $logger->debug('Registration hash lifetime is <= 0, kipping deletion.');
            return;
        }

        $interval = new DateInterval("PT{$lifetime}S");
        $cutoff = (new DataFactory())->clock()->utc()->now()->sub($interval);

        $deleted_hashes = $this->reg_hash_repository->deleteExpired($cutoff->getTimestamp(), $usr_id);

        $logger->info(sprintf(
            '%d inactive user objects eligible for deletion found and deleted (cutoff: %s, lifetime: %d s).',
            count($deleted_hashes),
            $cutoff->format(DateTimeInterface::ATOM),
            $lifetime
        ));

        $num_deleted_users = 0;
        foreach ($deleted_hashes as $deleted_hash) {
            $user = ilObjectFactory::getInstanceByObjId($deleted_hash->getUserId(), false);
            if (!($user instanceof ilObjUser)) {
                continue;
            }

            $created = DateTimeImmutable::createFromFormat(
                ilObjUser::DATABASE_DATE_FORMAT,
                (string) $deleted_hash->getCreationDate(),
                new DateTimeZone('UTC')
            );

            $logger->info(sprintf(
                'Deleting user (login: %s | id: %d) – expired dual opt-in (created: %s, cutoff: %s, lifetime: %d s)',
                $user->getLogin(),
                $user->getId(),
                $created ? $created->format(DateTimeInterface::ATOM) : '-',
                $cutoff->format(DateTimeInterface::ATOM),
                $lifetime
            ));
            $user->delete();
            ++$num_deleted_users;
        }

        $logger->info(sprintf(
            '%d inactive user objects with expired confirmation hash values (dual opt-in) deleted.',
            $num_deleted_users
        ));
    }

    private function activateUser(ilObjUser $user): void
    {
        $user->setActive(true);

        $settings = new ilRegistrationSettings();
        if ($settings->passwordGenerationEnabled()) {
            $password = ilSecuritySettingsChecker::generatePasswords(1)[0];
            $user->setPasswd($password, ilObjUser::PASSWD_PLAIN);
            $user->setLastPasswordChangeTS(time());
        } else {
            $password = '';
        }

        $user->update();

        $this->sendRegistrationMail($user, $settings, $password);
    }

    private function triggerExpiredUserCleanup(int $usr_id): void
    {
        $soap_client = new ilSoapClient();
        $soap_client->setResponseTimeout(1);
        $soap_client->enableWSDL(true);
        $soap_client->init();

        $logger = $this->dic->logger()->user();
        $logger->info(
            'Triggered soap call (background process) for deletion of inactive user objects with expired confirmation hash values (dual opt in) ...'
        );

        $sid = $_COOKIE[session_name()] . '::' . CLIENT_ID;
        $soap_client->call('deleteExpiredDualOptInUserObjects', [$sid, $usr_id]);
    }

    private function sendRegistrationMail(ilObjUser $user, ilRegistrationSettings $settings, string $password): void
    {
        $account_mail = (new ilAccountRegistrationMail(
            $settings,
            ilLoggerFactory::getLogger('user'),
            new NewAccountMailRepository($this->dic->database())
        ))->withEmailConfirmationRegistrationMode();

        if ($user->getPref('reg_target') ?? '') {
            $account_mail = $account_mail->withPermanentLinkTarget($user->getPref('reg_target'));
        }

        $account_mail->send($user, $password);
    }
}
