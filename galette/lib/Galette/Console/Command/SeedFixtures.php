<?php

/**
 * Copyright © 2003-2026 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Galette\Console\Command;

use Galette\Core\Db;
use Galette\Core\History;
use Galette\Core\Login;
use Galette\Core\Picture;
use Galette\Core\Preferences;
use Galette\DynamicFields\DynamicField;
use Galette\Entity\Adherent;
use Galette\Entity\Contribution;
use Galette\Entity\DynamicFieldsHandle;
use Galette\Entity\Group;
use Galette\Entity\Title;
use Galette\Entity\Transaction;
use Laminas\Db\Adapter\Driver\StatementInterface;
use Safe\DateTime;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Safe\fclose;
use function Safe\fopen;
use function Safe\fread;
use function Safe\imagecreatetruecolor;
use function Safe\imageellipse;
use function Safe\imagefilledrectangle;
use function Safe\imagepng;
use function Safe\imagestring;
use function Safe\unlink;

/**
 * Seed E2E test fixtures console command
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[AsCommand(
    name: 'galette:seed-fixtures',
    description: 'Seed database with E2E test fixtures (fictional members, contributions, groups, etc.)'
)]
class SeedFixtures extends AbstractCommand
{
    /** Fingerprint marker for idempotence */
    public const FIXTURE_FINGERPRINT = 'E2E_FIXTURE';

    /** Default password for all fixture members */
    public const FIXTURE_PASSWORD = 'G@l3tte-E2E!';

    private Db $zdb;
    private Login $login;
    private Preferences $preferences;
    private History $history;
    /** @var array<string,mixed> */
    private array $members_fields;

    /** @var array<string, int> Dynamic field IDs indexed by name */
    private array $dynamic_field_ids = [];

    /** @var array<string, Adherent> Created members indexed by login */
    private array $members = [];

    /** @var array<string, int> Created transaction IDs indexed by description */
    private array $transactions = [];
    private ?StatementInterface $insert_picture_stmt = null;

    /**
     * Configure command options
     */
    protected function configure(): void
    {
        $this
            ->addOption('clean', null, InputOption::VALUE_NONE, 'Remove all E2E fixtures and exit');
    }

    /**
     * Execute the command
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        global $zdb, $login, $preferences, $hist;

        $io = new SymfonyStyle($input, $output);
        $io->title('Galette E2E Fixtures Seeder');

        $this->zdb = $zdb;
        $this->login = $login;
        $this->preferences = $preferences;
        $this->history = $hist;

        $container = $GLOBALS['container'];
        $this->members_fields = $container->get('members_fields');

        // Ensure HTTP_HOST is set for CLI context (used by Preferences::getURL())
        if (!isset($_SERVER['HTTP_HOST'])) {
            $_SERVER['HTTP_HOST'] = 'localhost';
        }

        // Log in as superadmin for all operations
        $this->login->logAdmin('superadmin', $this->preferences);

        if ($input->getOption('clean')) {
            $this->cleanAll($io);
            $io->success('All E2E fixtures removed.');
            return Command::SUCCESS;
        }

        // Clean before seeding for idempotence
        $this->cleanAll($io);

        $io->section('Creating titles');
        $this->createTitles($io);

        $io->section('Creating dynamic fields');
        $this->createDynamicFields($io);

        $io->section('Creating members');
        $this->createMembers($io);

        $io->section('Generating and storing photos');
        $this->createPhotos($io);

        $io->section('Creating groups');
        $this->createGroups($io);

        $io->section('Creating transactions');
        $this->createTransactions($io);

        $io->section('Creating contributions');
        $this->createContributions($io);

        $io->section('Setting dynamic field values');
        $this->setDynamicFieldValues($io);

        $io->success(sprintf(
            'Fixtures seeded: %d members, %d transactions, dynamic fields configured.',
            count($this->members),
            count($this->transactions)
        ));

        return Command::SUCCESS;
    }

    /**
     * Remove all E2E fixture data
     */
    public function cleanAll(SymfonyStyle $io): void
    {
        // Get all fixture member IDs
        $select = $this->zdb->select(Adherent::TABLE);
        $select->columns(['id_adh']);
        $select->where(['fingerprint' => self::FIXTURE_FINGERPRINT]);
        $results = $this->zdb->execute($select);
        $member_ids = [];
        foreach ($results as $row) {
            $member_ids[] = (int)$row->id_adh;
        }

        if (count($member_ids) > 0) {
            // Delete dynamic field values for members
            $delete = $this->zdb->delete(DynamicFieldsHandle::TABLE);
            $delete->where->in('item_id', $member_ids);
            $delete->where->equalTo('field_form', 'adh');
            $this->zdb->execute($delete);

            // Get contribution IDs for these members
            $select = $this->zdb->select(Contribution::TABLE);
            $select->columns(['id_cotis']);
            $select->where->in('id_adh', $member_ids);
            $results = $this->zdb->execute($select);
            $contrib_ids = [];
            foreach ($results as $row) {
                $contrib_ids[] = (int)$row->id_cotis;
            }

            // Delete dynamic field values for contributions
            if (count($contrib_ids) > 0) {
                $delete = $this->zdb->delete(DynamicFieldsHandle::TABLE);
                $delete->where->in('item_id', $contrib_ids);
                $delete->where->equalTo('field_form', 'contrib');
                $this->zdb->execute($delete);
            }

            // Delete contributions
            $delete = $this->zdb->delete(Contribution::TABLE);
            $delete->where->in('id_adh', $member_ids);
            $this->zdb->execute($delete);

            // Delete transactions
            $delete = $this->zdb->delete(Transaction::TABLE);
            $delete->where->in('id_adh', $member_ids);
            $this->zdb->execute($delete);

            // Delete group memberships
            $delete = $this->zdb->delete(Group::GROUPSUSERS_TABLE);
            $delete->where->in('id_adh', $member_ids);
            $this->zdb->execute($delete);

            // Delete group managers
            $delete = $this->zdb->delete(Group::GROUPSMANAGERS_TABLE);
            $delete->where->in('id_adh', $member_ids);
            $this->zdb->execute($delete);

            // Delete pictures
            $delete = $this->zdb->delete(Picture::TABLE);
            $delete->where->in('id_adh', $member_ids);
            $this->zdb->execute($delete);

            // Delete photo files
            foreach ($member_ids as $id) {
                foreach (['jpg', 'png', 'gif', 'webp'] as $ext) {
                    $file = GALETTE_PHOTOS_PATH . $id . '.' . $ext;
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
            }

            // Delete members (children first due to parent_id FK)
            $delete = $this->zdb->delete(Adherent::TABLE);
            $delete->where(['fingerprint' => self::FIXTURE_FINGERPRINT]);
            $delete->where->isNotNull('parent_id');
            $this->zdb->execute($delete);

            $delete = $this->zdb->delete(Adherent::TABLE);
            $delete->where(['fingerprint' => self::FIXTURE_FINGERPRINT]);
            $this->zdb->execute($delete);
        }

        // Delete fixture groups (children first to respect FK)
        $group_defs = array_reverse($this->getGroupDefinitions());
        foreach ($group_defs as $group_def) {
            // First get group ID
            $select = $this->zdb->select(Group::TABLE);
            $select->columns(['id_group']);
            $select->where(['group_name' => $group_def['name']]);
            $results = $this->zdb->execute($select);
            foreach ($results as $row) {
                $gid = (int)$row->id_group;
                // Delete group members and managers first
                $delete = $this->zdb->delete(Group::GROUPSUSERS_TABLE);
                $delete->where(['id_group' => $gid]);
                $this->zdb->execute($delete);

                $delete = $this->zdb->delete(Group::GROUPSMANAGERS_TABLE);
                $delete->where(['id_group' => $gid]);
                $this->zdb->execute($delete);

                // Then delete the group
                $delete = $this->zdb->delete(Group::TABLE);
                $delete->where(['id_group' => $gid]);
                $this->zdb->execute($delete);
            }
        }

        // Delete fixture dynamic fields via DynamicField::remove() to also clean L10n translations
        foreach (array_merge($this->getAdhDynamicFieldDefinitions(), $this->getContribDynamicFieldDefinitions()) as $field_def) {
            $select = $this->zdb->select(DynamicField::TABLE);
            $select->where([
                'field_name' => $field_def['name'],
                'field_form' => $field_def['form'],
            ]);
            $results = $this->zdb->execute($select);
            foreach ($results as $row) {
                $field = DynamicField::getFieldType($this->zdb, (int)$row->field_type, (int)$row->field_id);
                $field->remove();
            }
        }

        // Delete history entries created during seeding (superadmin login)
        $delete = $this->zdb->delete(History::TABLE);
        $delete->where(['adh_log' => 'superadmin']);
        $this->zdb->execute($delete);

        // Delete fixture titles (only those we created, never MR/MRS)
        foreach ($this->getTitleDefinitions() as $title_def) {
            $delete = $this->zdb->delete(Title::TABLE);
            $delete->where([
                Title::PK => $title_def['id'],
                'short_label' => $title_def['short'],
            ]);
            $this->zdb->execute($delete);
        }

        $io->text('Cleaned existing fixture data.');
    }

    /**
     * Create custom titles for fixture members
     */
    private function createTitles(SymfonyStyle $io): void
    {
        foreach ($this->getTitleDefinitions() as $title_def) {
            // Check if title already exists
            $select = $this->zdb->select(Title::TABLE);
            $select->where([Title::PK => $title_def['id']]);
            $results = $this->zdb->execute($select);

            if ($results->count() === 0) {
                $insert = $this->zdb->insert(Title::TABLE);
                $insert->values([
                    Title::PK => $title_def['id'],
                    'short_label' => $title_def['short'],
                    'long_label' => $title_def['long'],
                ]);
                $this->zdb->execute($insert);
                $io->text(sprintf('  Created title: %s (%s)', $title_def['long'], $title_def['short']));
            } else {
                $io->text(sprintf('  Title already exists: %s', $title_def['short']));
            }
        }
    }

    /**
     * Get title definitions for fixture data
     *
     * @return array<int, array{id: int, short: string, long: string}>
     */
    private function getTitleDefinitions(): array
    {
        return [
            ['id' => 3, 'short' => 'Me', 'long' => 'Maître'],
            ['id' => 4, 'short' => 'Cap.', 'long' => 'Capitaine'],
        ];
    }

    /**
     * Create dynamic fields for members and contributions
     */
    private function createDynamicFields(SymfonyStyle $io): void
    {
        foreach ($this->getAdhDynamicFieldDefinitions() as $field_def) {
            $this->createOneDynamicField($field_def, $io);
        }

        foreach ($this->getContribDynamicFieldDefinitions() as $field_def) {
            $this->createOneDynamicField($field_def, $io);
        }
    }

    /**
     * Create a single dynamic field
     *
     * @param array<string,mixed> $field_def Field definition
     */
    private function createOneDynamicField(array $field_def, SymfonyStyle $io): void
    {
        $df = DynamicField::getFieldType($this->zdb, $field_def['type']);

        $values = [
            'field_name' => $field_def['name'],
            'field_perm' => DynamicField::USER_WRITE,
            'form_name' => $field_def['form'],
            'field_required' => 0,
            'field_width_in_forms' => $field_def['width_in_forms'] ?? 1,
        ];

        if (isset($field_def['fixed_values'])) {
            $values['fixed_values'] = implode("\n", $field_def['fixed_values']);
        }

        $stored = $df->store($values);
        if (!$stored) {
            $io->error(sprintf(
                'Failed to create dynamic field: %s — %s',
                $field_def['name'],
                implode(', ', $df->getErrors())
            ));
            return;
        }

        $this->dynamic_field_ids[$field_def['name']] = $df->getId();
        $io->text(sprintf('  Created field "%s" (ID: %d)', $field_def['name'], $df->getId()));
    }

    /**
     * Create all fixture members (parents first, then children)
     */
    private function createMembers(SymfonyStyle $io): void
    {
        // Create parents first
        foreach ($this->getParentMemberData() as $data) {
            $this->createOneMember($data, $io);
        }

        // Then children (need parent IDs)
        foreach ($this->getChildMemberData() as $data) {
            if (isset($data['parent_login'])) {
                $parent_login = $data['parent_login'];
                unset($data['parent_login']);
                if (isset($this->members[$parent_login])) {
                    $data['parent_id'] = $this->members[$parent_login]->id;
                }
            }
            $this->createOneMember($data, $io);
        }
    }

    /**
     * Create one member using Galette validation
     *
     * @param array<string,mixed> $data Member data
     */
    private function createOneMember(array $data, SymfonyStyle $io): void
    {
        // Extract fingerprint before check() (not handled by Adherent validation)
        $fingerprint = $data['fingerprint'] ?? null;
        unset($data['fingerprint']);

        $adh = new Adherent($this->zdb);
        $adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        $check = $adh->check($data, [], []);
        if (is_array($check)) {
            $io->error(sprintf('Validation failed for %s: %s', $data['login_adh'], implode(', ', $check)));
            return;
        }

        $store = $adh->store();
        if (!$store) {
            $io->error('Failed to store member: ' . $data['login_adh']);
            return;
        }

        // Set fingerprint directly in database for idempotence tracking
        if ($fingerprint !== null) {
            $update = $this->zdb->update(Adherent::TABLE);
            $update->set(['fingerprint' => $fingerprint]);
            $update->where(['id_adh' => $adh->id]);
            $this->zdb->execute($update);
        }

        $this->members[$data['login_adh']] = $adh;
        $io->text(sprintf('  Created member "%s %s" (ID: %d)', $data['nom_adh'], $data['prenom_adh'] ?? '', $adh->id));
    }

    /**
     * Generate and store avatar photos for members using GD
     */
    private function createPhotos(SymfonyStyle $io): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $io->warning('GD extension not available, skipping photo generation.');
            return;
        }

        $colors = [
            [231, 76, 60],    // red
            [46, 204, 113],   // green
            [52, 152, 219],   // blue
            [155, 89, 182],   // purple
            [241, 196, 15],   // yellow
            [230, 126, 34],   // orange
            [26, 188, 156],   // teal
            [52, 73, 94],     // dark blue
            [192, 57, 43],    // dark red
            [39, 174, 96],    // dark green
        ];

        $i = 0;
        foreach ($this->members as $adh) {
            $color = $colors[$i % count($colors)];
            $initials = $this->getInitials($adh);
            $filepath = $this->generateAvatar($adh->id, $initials, $color);
            if ($filepath !== null) {
                $this->storePicture($adh->id, $filepath);
            }
            $i++;
        }

        $io->text(sprintf('  Generated %d avatar photos.', count($this->members)));
    }

    /**
     * Get initials from an adherent
     */
    private function getInitials(Adherent $adh): string
    {
        $first = mb_substr($adh->name, 0, 1);
        $second = mb_substr((string)$adh->surname, 0, 1);
        return mb_strtoupper($first . $second);
    }

    /**
     * Generate a PNG avatar with initials
     *
     * @param int        $id       Member ID
     * @param string     $initials Initials to display
     * @param array<int> $color    RGB color array
     */
    private function generateAvatar(int $id, string $initials, array $color): ?string
    {
        $size = 200;
        $img = imagecreatetruecolor($size, $size);

        $bg = imagecolorallocate($img, $color[0], $color[1], $color[2]);
        $white = imagecolorallocate($img, 255, 255, 255);
        if ($bg === false || $white === false) {
            return null;
        }

        imagefilledrectangle($img, 0, 0, $size - 1, $size - 1, $bg);

        // Draw initials centered
        $font_size = 5; // largest built-in font
        $char_width = imagefontwidth($font_size);
        $char_height = imagefontheight($font_size);
        $text_width = $char_width * mb_strlen($initials);
        $x = (int)(($size - $text_width) / 2);
        $y = (int)(($size - $char_height) / 2);
        imagestring($img, $font_size, $x, $y, $initials, $white);

        // Draw a decorative circle
        imageellipse($img, (int)($size / 2), (int)($size / 2), $size - 20, $size - 20, $white);

        $filepath = GALETTE_PHOTOS_PATH . $id . '.png';
        imagepng($img, $filepath);

        return $filepath;
    }

    /**
     * Store a photo in the database for a member
     */
    private function storePicture(int $id, string $filepath): void
    {
        $f = fopen($filepath, 'r');
        $picture = '';
        while ($r = fread($f, 8192)) {
            $picture .= $r;
        }
        fclose($f);

        if ($this->insert_picture_stmt === null) {
            $insert = $this->zdb->insert(Picture::TABLE);
            $insert->values(
                [
                    Adherent::PK  => ':' . Adherent::PK,
                    'picture'   => ':picture',
                    'format'    => ':format'
                ]
            );
            $stmt = $this->zdb->sql->prepareStatementForSqlObject($insert);
            $container = $stmt->getParameterContainer();
            $container->offsetSet(
                'picture', //'picture',
                ':picture',
                $container::TYPE_LOB
            );
            $stmt->setParameterContainer($container);
            $this->insert_picture_stmt = $stmt;
        }

        // Direct insert into pictures table
        try {
            $this->insert_picture_stmt->execute(
                [
                    Adherent::PK  => $id,
                    'picture'     => $picture,
                    'format'      => 'png'
                ]
            );
        } catch (\Throwable) {
            // Picture might already exist, ignore
        }
    }

    /**
     * Create groups and assign managers/members
     */
    private function createGroups(SymfonyStyle $io): void
    {
        $created_groups = [];

        foreach ($this->getGroupDefinitions() as $group_def) {
            $group = new Group();
            $group->setLogin($this->login);
            $group->setName($group_def['name']);

            if (isset($group_def['parent']) && isset($created_groups[$group_def['parent']])) {
                $group->setParentGroup($created_groups[$group_def['parent']]->getId());
            }

            $stored = $group->store();
            if (!$stored) {
                $io->error('Failed to create group: ' . $group_def['name']);
                continue;
            }

            $created_groups[$group_def['name']] = $group;

            // Set managers
            $managers = [];
            foreach ($group_def['managers'] as $login) {
                if (isset($this->members[$login])) {
                    $managers[] = $this->members[$login];
                }
            }
            if (count($managers) > 0) {
                $group->setManagers($managers);
            }

            // Set members
            $group_members = [];
            foreach ($group_def['members'] as $login) {
                if (isset($this->members[$login])) {
                    $group_members[] = $this->members[$login];
                }
            }
            if (count($group_members) > 0) {
                $group->setMembers($group_members);
            }

            $io->text(sprintf(
                '  Created group "%s" (%d managers, %d members)',
                $group_def['name'],
                count($managers),
                count($group_members)
            ));
        }
    }

    /**
     * Create transactions
     */
    private function createTransactions(SymfonyStyle $io): void
    {
        foreach ($this->getTransactionData() as $trans_data) {
            $member_login = $trans_data['member_login'];
            unset($trans_data['member_login']);

            if (!isset($this->members[$member_login])) {
                continue;
            }

            $trans_data['id_adh'] = $this->members[$member_login]->id;

            $transaction = new Transaction($this->zdb, $this->login);
            $check = $transaction->check($trans_data, [], []);
            if ($check !== true) {
                $io->error(sprintf('Transaction validation failed for "%s": %s', $trans_data['trans_desc'], implode(', ', $check)));
                continue;
            }

            $stored = $transaction->store($this->history);
            if (!$stored) {
                $io->error('Failed to store transaction: ' . $trans_data['trans_desc']);
                continue;
            }

            $this->transactions[$trans_data['trans_desc']] = $transaction->id;
            $io->text(sprintf('  Created transaction "%s" (ID: %d)', $trans_data['trans_desc'], $transaction->id));
        }
    }

    /**
     * Create contributions
     */
    private function createContributions(SymfonyStyle $io): void
    {
        $count = 0;
        foreach ($this->getContributionData() as $contrib_data) {
            $member_login = $contrib_data['member_login'];
            unset($contrib_data['member_login']);

            if (!isset($this->members[$member_login])) {
                continue;
            }

            $contrib_data['id_adh'] = $this->members[$member_login]->id;

            // Link to transaction if specified
            if (isset($contrib_data['trans_desc'])) {
                $trans_desc = $contrib_data['trans_desc'];
                unset($contrib_data['trans_desc']);
                if (isset($this->transactions[$trans_desc])) {
                    $contrib_data['trans_id'] = $this->transactions[$trans_desc];
                }
            }

            $contrib = new Contribution(
                $this->zdb,
                $this->login,
                ['type' => $contrib_data['id_type_cotis']]
            );
            // Disable events to avoid mail/PDF generation in CLI context
            $contrib->disableEvents();
            $check = $contrib->check($contrib_data, $contrib->getRequired(), []);
            if (is_array($check)) {
                $io->error(sprintf('Contribution validation failed for %s: %s', $member_login, implode(', ', $check)));
                continue;
            }

            $stored = $contrib->store();
            if (!$stored) {
                $io->error('Failed to store contribution for: ' . $member_login);
                continue;
            }

            $count++;
        }

        $io->text(sprintf('  Created %d contributions.', $count));
    }

    /**
     * Set dynamic field values on members and contributions
     */
    private function setDynamicFieldValues(SymfonyStyle $io): void
    {
        $adh_count = 0;
        foreach ($this->getMemberDynamicValues() as $login => $field_values) {
            if (!isset($this->members[$login])) {
                continue;
            }
            $adh = $this->members[$login];
            $handle = new DynamicFieldsHandle($this->zdb, $this->login, $adh);

            foreach ($field_values as $field_name => $value) {
                if (!isset($this->dynamic_field_ids[$field_name])) {
                    continue;
                }
                $field_id = $this->dynamic_field_ids[$field_name];
                $handle->setValue($adh->id, $field_id, 1, $value);
            }

            $handle->storeValues($adh->id);
            $adh_count++;
        }

        $io->text(sprintf('  Set dynamic values for %d members.', $adh_count));
    }

    /**
     * Get dynamic field definitions for members
     *
     * @return array<array<string,mixed>>
     */
    private function getAdhDynamicFieldDefinitions(): array
    {
        return [
            [
                'name' => 'Univers fictif d\'origine',
                'form' => 'adh',
                'type' => DynamicField::CHOICE,
                'width_in_forms' => 6,
                'fixed_values' => ['Star Wars', 'Matrix', 'Game of Thrones', 'Futurama', 'Les Cités d\'Or', 'Années 80', 'Ulysse 31', 'Tim Burton', 'Harry Potter', 'Le Seigneur des Anneaux', 'Les Simpsons', 'Astérix', 'Retour vers le futur', 'Inspecteur Gadget', 'X-Files', 'Ghostbusters', 'Kaamelott'],
            ],
            [
                'name' => 'Niveau de la Force (midichlorians)',
                'form' => 'adh',
                'type' => DynamicField::LINE,
                'width_in_forms' => 3,
            ],
            [
                'name' => 'Phrase culte',
                'form' => 'adh',
                'type' => DynamicField::TEXT,
                'width_in_forms' => 6,
            ],
            [
                'name' => 'Date d\'entrée au conseil galactique',
                'form' => 'adh',
                'type' => DynamicField::DATE,
                'width_in_forms' => 3,
            ],
            [
                'name' => 'Membre du Conseil Jedi',
                'form' => 'adh',
                'type' => DynamicField::BOOLEAN,
                'width_in_forms' => 3,
            ],
        ];
    }

    /**
     * Get dynamic field definitions for contributions
     *
     * @return array<array<string,mixed>>
     */
    private function getContribDynamicFieldDefinitions(): array
    {
        return [
            [
                'name' => 'Mode de paiement alternatif',
                'form' => 'contrib',
                'type' => DynamicField::LINE,
                'width_in_forms' => 6,
            ],
            [
                'name' => 'Référence de quête',
                'form' => 'contrib',
                'type' => DynamicField::LINE,
                'width_in_forms' => 6,
            ],
            [
                'name' => 'Niveau de satisfaction',
                'form' => 'contrib',
                'type' => DynamicField::CHOICE,
                'width_in_forms' => 4,
                'fixed_values' => ['Extatique', 'Content', 'Neutre', 'Bof', 'Côté obscur'],
            ],
        ];
    }

    /**
     * Get parent member data
     *
     * @return array<array<string,mixed>>
     */
    private function getParentMemberData(): array
    {
        $common = [
            'mdp_adh' => self::FIXTURE_PASSWORD,
            'mdp_adh2' => self::FIXTURE_PASSWORD,
            'fingerprint' => self::FIXTURE_FINGERPRINT,
            'activite_adh' => true,
            'bool_display_info' => true,
            'pref_lang' => 'fr_FR',
        ];

        return [
            // Star Wars
            array_merge($common, [
                'nom_adh' => 'Skywalker', 'prenom_adh' => 'Luke',
                'login_adh' => 'luke.skywalker', 'email_adh' => 'luke.skywalker@rebellion.org',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 4, 'date_crea_adh' => '2024-01-15',
                'ddn_adh' => '1977-05-25', 'lieu_naissance' => 'Polis Massa',
                'ville_adh' => 'Mos Eisley', 'pays_adh' => 'Tatooine', 'cp_adh' => '00001',
                'adresse_adh' => 'Ferme des Lars, désert de Jundland',
                'pseudo_adh' => 'redjedi', 'prof_adh' => 'Chevalier Jedi',
                'tel_adh' => '0142424242', 'gsm_adh' => '0601020304',
            ]),
            array_merge($common, [
                'nom_adh' => 'Organa', 'prenom_adh' => 'Leia',
                'login_adh' => 'leia.organa', 'email_adh' => 'leia.organa@rebellion.org',
                'sexe_adh' => Adherent::WOMAN, 'titre_adh' => 2,
                'id_statut' => 1, 'date_crea_adh' => '2023-06-10',
                'ddn_adh' => '1977-05-25', 'lieu_naissance' => 'Polis Massa',
                'ville_adh' => 'Aldera', 'pays_adh' => 'Alderaan', 'cp_adh' => '00002',
                'adresse_adh' => 'Palais Royal d\'Alderaan',
                'pseudo_adh' => 'generalorgana', 'prof_adh' => 'Générale',
                'bool_admin_adh' => true,
            ]),
            array_merge($common, [
                'nom_adh' => 'Solo', 'prenom_adh' => 'Han',
                'login_adh' => 'han.solo', 'email_adh' => 'han.solo@faucon-millenium.net',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 4, 'date_crea_adh' => '2024-03-20',
                'ddn_adh' => '1973-03-15', 'lieu_naissance' => 'Corellia',
                'ville_adh' => 'Corellia City', 'pays_adh' => 'Corellia', 'cp_adh' => '00003',
                'adresse_adh' => 'Quai d\'amarrage 94, baie du Faucon',
                'pseudo_adh' => 'nerf_herder', 'prof_adh' => 'Contrebandier',
            ]),
            array_merge($common, [
                'nom_adh' => 'Kenobi', 'prenom_adh' => 'Obi-Wan',
                'login_adh' => 'obiwan.kenobi', 'email_adh' => 'obiwan@jedi-council.org',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 6, 'date_crea_adh' => '2023-01-01',
                'ddn_adh' => '1950-03-25', 'lieu_naissance' => 'Stewjon',
                'ville_adh' => 'Theed', 'pays_adh' => 'Naboo', 'cp_adh' => '00004',
                'adresse_adh' => 'Temple Jedi, secteur du Conseil',
                'pseudo_adh' => 'benmyonlyhope', 'prof_adh' => 'Maître Jedi',
            ]),
            // Matrix
            array_merge($common, [
                'nom_adh' => 'Anderson', 'prenom_adh' => 'Neo',
                'login_adh' => 'neo.anderson', 'email_adh' => 'neo@matrixreloaded.io',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 5, 'date_crea_adh' => '2023-09-01',
                'ddn_adh' => '1964-09-13', 'lieu_naissance' => 'Capital City',
                'ville_adh' => 'Mega City One', 'pays_adh' => 'Matrice', 'cp_adh' => '10001',
                'adresse_adh' => 'Appartement 101, tour Metacortex',
                'pseudo_adh' => 'theone', 'prof_adh' => 'Élu',
            ]),
            array_merge($common, [
                'nom_adh' => 'Morpheus', 'prenom_adh' => '',
                'login_adh' => 'morpheus', 'email_adh' => 'morpheus@nebuchadnezzar.net',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 2, 'date_crea_adh' => '2023-09-01',
                'ddn_adh' => '1961-07-30', 'lieu_naissance' => 'Zion',
                'ville_adh' => 'Zion', 'pays_adh' => 'Matrice', 'cp_adh' => '10002',
                'adresse_adh' => 'Dock 5, niveau inférieur, Zion',
                'pseudo_adh' => 'pillule_rouge', 'prof_adh' => 'Capitaine',
            ]),
            array_merge($common, [
                'nom_adh' => 'Trinity', 'prenom_adh' => '',
                'login_adh' => 'trinity', 'email_adh' => 'trinity@matrixreloaded.io',
                'sexe_adh' => Adherent::WOMAN, 'titre_adh' => 2,
                'id_statut' => 4, 'date_crea_adh' => '2024-02-14',
                'ddn_adh' => '1967-01-15', 'lieu_naissance' => 'Mega City One',
                'ville_adh' => 'Mega City One', 'pays_adh' => 'Matrice', 'cp_adh' => '10003',
                'adresse_adh' => 'Sous-réseau 7, noeud d\'accès Trinity',
                'pseudo_adh' => 'trinity_hack', 'prof_adh' => 'Hacker',
            ]),
            // Game of Thrones
            array_merge($common, [
                'nom_adh' => 'Stark', 'prenom_adh' => 'Arya',
                'login_adh' => 'arya.stark', 'email_adh' => 'arya.stark@winterfell.ws',
                'sexe_adh' => Adherent::WOMAN, 'titre_adh' => 3,
                'id_statut' => 4, 'date_crea_adh' => '2024-07-22',
                'ddn_adh' => '2000-04-17', 'lieu_naissance' => 'Winterfell',
                'ville_adh' => 'Winterfell', 'pays_adh' => 'Westeros', 'cp_adh' => '20001',
                'adresse_adh' => 'Château de Winterfell, aile nord',
                'pseudo_adh' => 'noonegirl', 'prof_adh' => 'Sans-Visage',
            ]),
            array_merge($common, [
                'nom_adh' => 'Targaryen', 'prenom_adh' => 'Daenerys',
                'login_adh' => 'daenerys.targaryen', 'email_adh' => 'daenerys@dracarys.ws',
                'sexe_adh' => Adherent::WOMAN, 'titre_adh' => 2,
                'id_statut' => 10, 'date_crea_adh' => '2023-11-05',
                'ddn_adh' => '1995-10-23', 'lieu_naissance' => 'Peyredragon',
                'ville_adh' => 'Meereen', 'pays_adh' => 'Essos', 'cp_adh' => '20002',
                'adresse_adh' => 'Grande Pyramide de Meereen',
                'pseudo_adh' => 'dracarys', 'prof_adh' => 'Reine',
            ]),
            array_merge($common, [
                'nom_adh' => 'Snow', 'prenom_adh' => 'Jon',
                'login_adh' => 'jon.snow', 'email_adh' => 'jon.snow@winterfell.ws',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 4, 'date_crea_adh' => '2024-08-01',
                'ddn_adh' => '1996-12-21', 'lieu_naissance' => 'Tour de la Joie',
                'ville_adh' => 'Winterfell', 'pays_adh' => 'Westeros', 'cp_adh' => '20003',
                'adresse_adh' => 'La Garde de Nuit, Château-Noir',
                'pseudo_adh' => 'kinginthenorth', 'prof_adh' => 'Lord Commandant',
            ]),
            // Futurama
            array_merge($common, [
                'nom_adh' => 'Fry', 'prenom_adh' => 'Philip J.',
                'login_adh' => 'philip.fry', 'email_adh' => 'fry@planetexpress.future',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 4, 'date_crea_adh' => '2025-01-10',
                'ddn_adh' => '1974-08-14', 'lieu_naissance' => 'Brooklyn',
                'ville_adh' => 'New New York', 'pays_adh' => 'USA (3000)', 'cp_adh' => '30001',
                'adresse_adh' => 'Planet Express, 57th Street',
                'pseudo_adh' => 'pizzaboy', 'prof_adh' => 'Livreur',
            ]),
            array_merge($common, [
                'nom_adh' => 'Leela', 'prenom_adh' => 'Turanga',
                'login_adh' => 'turanga.leela', 'email_adh' => 'leela@planetexpress.future',
                'sexe_adh' => Adherent::WOMAN, 'titre_adh' => 4,
                'id_statut' => 3, 'date_crea_adh' => '2024-05-15',
                'ddn_adh' => '1980-03-28', 'lieu_naissance' => 'Égouts de New New York',
                'ville_adh' => 'New New York', 'pays_adh' => 'USA (3000)', 'cp_adh' => '30002',
                'adresse_adh' => 'Planet Express, passerelle du vaisseau',
                'pseudo_adh' => 'cyclope_captain', 'prof_adh' => 'Capitaine de vaisseau',
            ]),
            array_merge($common, [
                'nom_adh' => 'Rodriguez', 'prenom_adh' => 'Bender',
                'login_adh' => 'bender.rodriguez', 'email_adh' => 'bender@bitemyshinyass.bot',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 7, 'date_crea_adh' => '2023-04-01',
                'ddn_adh' => '1996-01-01', 'lieu_naissance' => 'Tijuana',
                'ville_adh' => 'Tijuana', 'pays_adh' => 'Mexique', 'cp_adh' => '30003',
                'adresse_adh' => 'Planet Express, placard à balais',
                'pseudo_adh' => 'bender_is_great', 'prof_adh' => 'Robot plieuse',
            ]),
            // Les Cités d'Or
            array_merge($common, [
                'nom_adh' => 'Doré', 'prenom_adh' => 'Esteban',
                'login_adh' => 'esteban.dore', 'email_adh' => 'esteban@citesdor.soleil',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 4, 'date_crea_adh' => '2024-09-01',
                'ddn_adh' => '1982-09-01', 'lieu_naissance' => 'Barcelone',
                'ville_adh' => 'Barcelone', 'pays_adh' => 'Espagne', 'cp_adh' => '08001',
                'adresse_adh' => 'Port de Barcelone, quai des Aventuriers',
                'pseudo_adh' => 'enfant_soleil', 'prof_adh' => 'Explorateur',
            ]),
            array_merge($common, [
                'nom_adh' => 'Doré', 'prenom_adh' => 'Zia',
                'login_adh' => 'zia.dore', 'email_adh' => 'zia@citesdor.soleil',
                'sexe_adh' => Adherent::WOMAN, 'titre_adh' => 3,
                'id_statut' => 4, 'date_crea_adh' => '2024-09-01',
                'ddn_adh' => '1983-02-14', 'lieu_naissance' => 'Cuzco',
                'ville_adh' => 'Cuzco', 'pays_adh' => 'Pérou', 'cp_adh' => '08002',
                'adresse_adh' => 'Temple du Soleil, vallée sacrée',
                'pseudo_adh' => 'zia_inca', 'prof_adh' => 'Fille du Soleil',
            ]),
            // Années 80
            array_merge($common, [
                'nom_adh' => 'Maestro', 'prenom_adh' => '',
                'login_adh' => 'maestro', 'email_adh' => 'maestro@iletaitunefois.tv',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 6, 'date_crea_adh' => '2023-01-01',
                'ddn_adh' => '1940-01-01', 'lieu_naissance' => 'Paris',
                'ville_adh' => 'Paris', 'pays_adh' => 'France', 'cp_adh' => '75001',
                'adresse_adh' => 'Bibliothèque du Savoir Universel',
                'pseudo_adh' => 'barbe_blanche', 'prof_adh' => 'Sage éternel',
            ]),
            // Ulysse 31
            array_merge($common, [
                'nom_adh' => 'Ulysse', 'prenom_adh' => '',
                'login_adh' => 'ulysse', 'email_adh' => 'ulysse@olympe.space',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 4,
                'id_statut' => 4, 'date_crea_adh' => '2024-04-01',
                'ddn_adh' => '1981-10-07', 'lieu_naissance' => 'Ithaque Spatiale',
                'ville_adh' => 'Ithaque Spatiale', 'pays_adh' => 'Grèce Galactique', 'cp_adh' => '31000',
                'adresse_adh' => 'Odysseus, pont de commandement',
                'pseudo_adh' => 'odysseus31', 'prof_adh' => 'Capitaine spatial',
            ]),
            array_merge($common, [
                'nom_adh' => 'Nono', 'prenom_adh' => '',
                'login_adh' => 'nono', 'email_adh' => 'nono@olympe.space',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 4, 'date_crea_adh' => '2024-04-01',
                'ddn_adh' => '1981-10-07', 'lieu_naissance' => 'Ithaque Spatiale',
                'ville_adh' => 'Olympe', 'pays_adh' => 'Grèce Galactique', 'cp_adh' => '31001',
                'adresse_adh' => 'Odysseus, salle des machines',
                'pseudo_adh' => 'petit_robot', 'prof_adh' => 'Robot compagnon',
            ]),
            // Tim Burton
            array_merge($common, [
                'nom_adh' => 'Skellington', 'prenom_adh' => 'Jack',
                'login_adh' => 'jack.skellington', 'email_adh' => 'jack@halloween-town.spooky',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 1, 'date_crea_adh' => '2023-10-31',
                'ddn_adh' => '1993-10-29', 'lieu_naissance' => 'Halloween Town',
                'ville_adh' => 'Halloween Town', 'pays_adh' => 'Mondes de Burton', 'cp_adh' => '66600',
                'adresse_adh' => 'Colline Spirale, manoir Skellington',
                'pseudo_adh' => 'pumpkin_king', 'prof_adh' => 'Roi des Citrouilles',
            ]),
            array_merge($common, [
                'nom_adh' => 'Ragdoll', 'prenom_adh' => 'Sally',
                'login_adh' => 'sally.ragdoll', 'email_adh' => 'sally@halloween-town.spooky',
                'sexe_adh' => Adherent::WOMAN, 'titre_adh' => 2,
                'id_statut' => 4, 'date_crea_adh' => '2024-01-01',
                'ddn_adh' => '1993-10-29', 'lieu_naissance' => 'Laboratoire du Dr. Finkelstein',
                'ville_adh' => 'Halloween Town', 'pays_adh' => 'Mondes de Burton', 'cp_adh' => '66601',
                'adresse_adh' => 'Tour du Dr. Finkelstein',
                'pseudo_adh' => 'sally_stitches', 'prof_adh' => 'Herboriste',
            ]),
            array_merge($common, [
                'nom_adh' => 'Van Dort', 'prenom_adh' => 'Victor',
                'login_adh' => 'victor.vandort', 'email_adh' => 'victor@noces-funebres.mort',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 4, 'date_crea_adh' => '2024-06-15',
                'ddn_adh' => '2005-09-23', 'lieu_naissance' => 'Village Victorien',
                'ville_adh' => 'Village Victorien', 'pays_adh' => 'Mondes de Burton', 'cp_adh' => '66602',
                'adresse_adh' => 'Manoir Van Dort, rue des Poissonniers',
                'pseudo_adh' => 'victor_piano', 'prof_adh' => 'Pianiste',
            ]),
            array_merge($common, [
                'nom_adh' => 'la Mariée', 'prenom_adh' => 'Emily',
                'login_adh' => 'emily.lamariee', 'email_adh' => 'emily@noces-funebres.mort',
                'sexe_adh' => Adherent::WOMAN, 'titre_adh' => 2,
                'id_statut' => 4, 'date_crea_adh' => '2024-06-15',
                'ddn_adh' => '2005-09-23', 'lieu_naissance' => 'Royaume des Morts',
                'ville_adh' => 'Royaume des Morts', 'pays_adh' => 'Mondes de Burton', 'cp_adh' => '66603',
                'adresse_adh' => 'Sous le vieux chêne, Forêt des Morts',
                'pseudo_adh' => 'corpse_bride', 'prof_adh' => 'Mariée éternelle',
            ]),
            // Harry Potter
            array_merge($common, [
                'nom_adh' => 'Granger', 'prenom_adh' => 'Hermione',
                'login_adh' => 'hermione.granger', 'email_adh' => 'hermione@poudlard.sorcier',
                'sexe_adh' => Adherent::WOMAN, 'titre_adh' => 2,
                'id_statut' => 4, 'date_crea_adh' => '2023-09-01',
                'ddn_adh' => '1979-09-19', 'lieu_naissance' => 'Londres',
                'ville_adh' => 'Pré-au-Lard', 'pays_adh' => 'Angleterre magique', 'cp_adh' => '40001',
                'adresse_adh' => 'Château de Poudlard, tour de Gryffondor',
                'pseudo_adh' => 'bookworm_witch', 'prof_adh' => 'Auror',
            ]),
            array_merge($common, [
                'nom_adh' => 'Weasley', 'prenom_adh' => 'Ron',
                'login_adh' => 'ron.weasley', 'email_adh' => 'ron@famille-weasley.sorcier',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 4, 'date_crea_adh' => '2023-09-01',
                'ddn_adh' => '1980-03-01', 'lieu_naissance' => 'Ottery St Catchpole',
                'ville_adh' => 'Ottery St Catchpole', 'pays_adh' => 'Angleterre magique', 'cp_adh' => '40002',
                'adresse_adh' => 'Le Terrier, Ottery St Catchpole',
                'pseudo_adh' => 'chess_master', 'prof_adh' => 'Auror',
            ]),
            array_merge($common, [
                'nom_adh' => 'Dumbledore', 'prenom_adh' => 'Albus Perceval',
                'login_adh' => 'albus.dumbledore', 'email_adh' => 'directeur@poudlard.sorcier',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 6, 'date_crea_adh' => '2023-01-01',
                'ddn_adh' => '1881-08-01', 'lieu_naissance' => 'Mould-on-the-Wold',
                'ville_adh' => 'Poudlard', 'pays_adh' => 'Angleterre magique', 'cp_adh' => '40003',
                'adresse_adh' => 'Bureau du Directeur, Château de Poudlard',
                'pseudo_adh' => 'sherbet_lemon', 'prof_adh' => 'Directeur de Poudlard',
            ]),
            // Le Seigneur des Anneaux
            array_merge($common, [
                'nom_adh' => 'Sacquet', 'prenom_adh' => 'Frodon',
                'login_adh' => 'frodon.sacquet', 'email_adh' => 'frodon@comte.tva',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 4, 'date_crea_adh' => '2024-03-01',
                'ddn_adh' => '1972-09-22', 'lieu_naissance' => 'Hobbiton',
                'ville_adh' => 'Hobbiton', 'pays_adh' => 'Terre du Milieu', 'cp_adh' => '40011',
                'adresse_adh' => 'Cul-de-Sac, Hobbiton, La Comté',
                'pseudo_adh' => 'anneau_porteur', 'prof_adh' => 'Aventurier',
            ]),
            array_merge($common, [
                'nom_adh' => 'Mithrandir', 'prenom_adh' => 'Gandalf',
                'login_adh' => 'gandalf', 'email_adh' => 'gandalf@istari.tva',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 6, 'date_crea_adh' => '2023-01-15',
                'ddn_adh' => '1900-01-01', 'lieu_naissance' => 'Valinor',
                'ville_adh' => 'Minas Tirith', 'pays_adh' => 'Terre du Milieu', 'cp_adh' => '40012',
                'adresse_adh' => 'La Route, en chemin',
                'pseudo_adh' => 'you_shall_not_pass', 'prof_adh' => 'Istari',
            ]),
            array_merge($common, [
                'nom_adh' => 'Sylvain', 'prenom_adh' => 'Legolas',
                'login_adh' => 'legolas.sylvain', 'email_adh' => 'legolas@foret-noire.tva',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 4, 'date_crea_adh' => '2024-03-01',
                'ddn_adh' => '1900-01-01', 'lieu_naissance' => 'Forêt Noire',
                'ville_adh' => 'Forêt Noire', 'pays_adh' => 'Terre du Milieu', 'cp_adh' => '40013',
                'adresse_adh' => 'Palais du Roi Thranduil, Forêt Noire',
                'pseudo_adh' => 'elven_archer', 'prof_adh' => 'Prince Elfique',
            ]),
            // Les Simpsons
            array_merge($common, [
                'nom_adh' => 'Simpson', 'prenom_adh' => 'Homer Jay',
                'login_adh' => 'homer.simpson', 'email_adh' => 'homer@springfield.doh',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 4, 'date_crea_adh' => '2024-05-01',
                'ddn_adh' => '1956-05-12', 'lieu_naissance' => 'Springfield',
                'ville_adh' => 'Springfield', 'pays_adh' => 'États-Unis', 'cp_adh' => '40021',
                'adresse_adh' => '742 Evergreen Terrace, Springfield',
                'pseudo_adh' => 'doh_man', 'prof_adh' => 'Agent de sûreté nucléaire',
            ]),
            array_merge($common, [
                'nom_adh' => 'Simpson', 'prenom_adh' => 'Bart',
                'login_adh' => 'bart.simpson', 'email_adh' => 'bart@springfield.doh',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 9, 'date_crea_adh' => '2024-05-01',
                'ddn_adh' => '2010-02-23', 'lieu_naissance' => 'Springfield',
                'ville_adh' => 'Springfield', 'pays_adh' => 'États-Unis', 'cp_adh' => '40022',
                'adresse_adh' => '742 Evergreen Terrace, Springfield',
                'pseudo_adh' => 'el_barto', 'prof_adh' => 'Écolier',
            ]),
            array_merge($common, [
                'nom_adh' => 'Simpson', 'prenom_adh' => 'Lisa Marie',
                'login_adh' => 'lisa.simpson', 'email_adh' => 'lisa@springfield.doh',
                'sexe_adh' => Adherent::WOMAN, 'titre_adh' => 3,
                'id_statut' => 9, 'date_crea_adh' => '2024-05-01',
                'ddn_adh' => '2012-05-09', 'lieu_naissance' => 'Springfield',
                'ville_adh' => 'Springfield', 'pays_adh' => 'États-Unis', 'cp_adh' => '40023',
                'adresse_adh' => '742 Evergreen Terrace, Springfield',
                'pseudo_adh' => 'sax_prodigy', 'prof_adh' => 'Écolière surdouée',
            ]),
            // Astérix
            array_merge($common, [
                'nom_adh' => 'Astérix', 'prenom_adh' => '',
                'login_adh' => 'asterix', 'email_adh' => 'asterix@village-gaulois.gaule',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 4, 'date_crea_adh' => '2024-06-01',
                'ddn_adh' => '1960-10-29', 'lieu_naissance' => 'Armorique',
                'ville_adh' => 'Village gaulois', 'pays_adh' => 'Gaule', 'cp_adh' => '29000',
                'adresse_adh' => 'Hutte du chef, Village gaulois',
                'pseudo_adh' => 'petit_guerrier', 'prof_adh' => 'Guerrier gaulois',
            ]),
            array_merge($common, [
                'nom_adh' => 'Obélix', 'prenom_adh' => '',
                'login_adh' => 'obelix', 'email_adh' => 'obelix@village-gaulois.gaule',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 4, 'date_crea_adh' => '2024-06-01',
                'ddn_adh' => '1961-08-17', 'lieu_naissance' => 'Armorique',
                'ville_adh' => 'Village gaulois', 'pays_adh' => 'Gaule', 'cp_adh' => '29001',
                'adresse_adh' => 'Hutte d\'Obélix, Village gaulois',
                'pseudo_adh' => 'tonneau_vivant', 'prof_adh' => 'Livreur de menhirs',
            ]),
            array_merge($common, [
                'nom_adh' => 'Panoramix', 'prenom_adh' => '',
                'login_adh' => 'panoramix', 'email_adh' => 'panoramix@village-gaulois.gaule',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 6, 'date_crea_adh' => '2024-06-01',
                'ddn_adh' => '1920-01-01', 'lieu_naissance' => 'Armorique',
                'ville_adh' => 'Village gaulois', 'pays_adh' => 'Gaule', 'cp_adh' => '29002',
                'adresse_adh' => 'Hutte du druide, Village gaulois',
                'pseudo_adh' => 'druide_sage', 'prof_adh' => 'Druide',
            ]),
            // Kaamelott
            array_merge($common, [
                'nom_adh' => 'Pendragon', 'prenom_adh' => 'Arthur',
                'login_adh' => 'arthur.pendragon', 'email_adh' => 'arthur@kaamelott.bretagne',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 1, 'date_crea_adh' => '2024-07-15',
                'ddn_adh' => '1975-03-15', 'lieu_naissance' => 'Kaamelott',
                'ville_adh' => 'Kaamelott', 'pays_adh' => 'Bretagne', 'cp_adh' => '56000',
                'adresse_adh' => 'Château de Kaamelott, salle du trône',
                'pseudo_adh' => 'roi_arthur', 'prof_adh' => 'Roi de Bretagne',
            ]),
            array_merge($common, [
                'nom_adh' => 'Gallois', 'prenom_adh' => 'Perceval',
                'login_adh' => 'perceval.gallois', 'email_adh' => 'perceval@kaamelott.bretagne',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 4, 'date_crea_adh' => '2024-07-15',
                'ddn_adh' => '1978-07-20', 'lieu_naissance' => 'Galles',
                'ville_adh' => 'Kaamelott', 'pays_adh' => 'Bretagne', 'cp_adh' => '56001',
                'adresse_adh' => 'Château de Kaamelott, salle de la Table Ronde',
                'pseudo_adh' => 'chevalier_gallois', 'prof_adh' => 'Chevalier',
            ]),
            array_merge($common, [
                'nom_adh' => 'Lancelot', 'prenom_adh' => 'Léodagan de',
                'login_adh' => 'leodagan.lancelot', 'email_adh' => 'leodagan@kaamelott.bretagne',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 4, 'date_crea_adh' => '2024-07-15',
                'ddn_adh' => '1950-10-10', 'lieu_naissance' => 'Carmelide',
                'ville_adh' => 'Kaamelott', 'pays_adh' => 'Bretagne', 'cp_adh' => '56002',
                'adresse_adh' => 'Château de Carmelide, forteresse père d\'Ygerne',
                'pseudo_adh' => 'beau_pere', 'prof_adh' => 'Seigneur de Carmelide',
            ]),
            // Retour vers le futur
            array_merge($common, [
                'nom_adh' => 'McFly', 'prenom_adh' => 'Marty',
                'login_adh' => 'marty.mcfly', 'email_adh' => 'marty@hill-valley.bttf',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 4, 'date_crea_adh' => '2024-10-26',
                'ddn_adh' => '1968-06-09', 'lieu_naissance' => 'Hill Valley',
                'ville_adh' => 'Hill Valley', 'pays_adh' => 'États-Unis', 'cp_adh' => '40041',
                'adresse_adh' => '9303 Lyon Drive, Hill Valley',
                'pseudo_adh' => 'future_boy', 'prof_adh' => 'Lycéen et guitariste',
            ]),
            array_merge($common, [
                'nom_adh' => 'Brown', 'prenom_adh' => 'Emmett',
                'login_adh' => 'doc.brown', 'email_adh' => 'doc@delorean-labs.bttf',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 6, 'date_crea_adh' => '2024-10-26',
                'ddn_adh' => '1920-01-01', 'lieu_naissance' => 'Hill Valley',
                'ville_adh' => 'Hill Valley', 'pays_adh' => 'États-Unis', 'cp_adh' => '40042',
                'adresse_adh' => 'Laboratoire Brown, Route de la Falaise',
                'pseudo_adh' => 'great_scott', 'prof_adh' => 'Scientifique excentrique',
            ]),
            // Inspecteur Gadget
            array_merge($common, [
                'nom_adh' => 'Gadget', 'prenom_adh' => '',
                'login_adh' => 'inspecteur.gadget', 'email_adh' => 'gadget@metro-city.police',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 4, 'date_crea_adh' => '2023-09-01',
                'ddn_adh' => '1942-01-01', 'lieu_naissance' => 'Métro City',
                'ville_adh' => 'Métro City', 'pays_adh' => 'Pays imaginaire', 'cp_adh' => '40051',
                'adresse_adh' => '1 Police Plaza, Métro City',
                'pseudo_adh' => 'wowserwowser', 'prof_adh' => 'Inspecteur',
            ]),
            array_merge($common, [
                'nom_adh' => 'Parker', 'prenom_adh' => 'Penny',
                'login_adh' => 'penny.parker', 'email_adh' => 'penny@metro-city.police',
                'sexe_adh' => Adherent::WOMAN, 'titre_adh' => 3,
                'id_statut' => 9, 'date_crea_adh' => '2023-09-01',
                'ddn_adh' => '2008-06-01', 'lieu_naissance' => 'Métro City',
                'ville_adh' => 'Métro City', 'pays_adh' => 'Pays imaginaire', 'cp_adh' => '40052',
                'adresse_adh' => '1 Police Plaza, Métro City',
                'pseudo_adh' => 'computer_book', 'prof_adh' => 'Détective junior',
            ]),
            // X-Files
            array_merge($common, [
                'nom_adh' => 'Mulder', 'prenom_adh' => 'Fox',
                'login_adh' => 'fox.mulder', 'email_adh' => 'fox.mulder@fbi.gov',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 4, 'date_crea_adh' => '2024-01-10',
                'ddn_adh' => '1961-10-13', 'lieu_naissance' => 'Chilmark',
                'ville_adh' => 'Washington DC', 'pays_adh' => 'États-Unis', 'cp_adh' => '40061',
                'adresse_adh' => 'FBI, Section X, Sous-sol, Washington',
                'pseudo_adh' => 'i_want_to_believe', 'prof_adh' => 'Agent spécial FBI',
            ]),
            array_merge($common, [
                'nom_adh' => 'Scully', 'prenom_adh' => 'Dana',
                'login_adh' => 'dana.scully', 'email_adh' => 'dana.scully@fbi.gov',
                'sexe_adh' => Adherent::WOMAN, 'titre_adh' => 2,
                'id_statut' => 4, 'date_crea_adh' => '2024-01-10',
                'ddn_adh' => '1964-02-23', 'lieu_naissance' => 'Ellison',
                'ville_adh' => 'Washington DC', 'pays_adh' => 'États-Unis', 'cp_adh' => '40062',
                'adresse_adh' => 'FBI, Section X, Sous-sol, Washington',
                'pseudo_adh' => 'skeptic_doctor', 'prof_adh' => 'Agent spécial FBI / Médecin',
            ]),
            // Ghostbusters
            array_merge($common, [
                'nom_adh' => 'Venkman', 'prenom_adh' => 'Peter',
                'login_adh' => 'peter.venkman', 'email_adh' => 'peter@ghostbusters.nyc',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 4, 'date_crea_adh' => '2024-07-04',
                'ddn_adh' => '1957-02-14', 'lieu_naissance' => 'New York',
                'ville_adh' => 'New York', 'pays_adh' => 'États-Unis', 'cp_adh' => '40071',
                'adresse_adh' => 'Caserne des SOS Fantômes, Hook & Ladder 8, Tribeca',
                'pseudo_adh' => 'dr_venkman', 'prof_adh' => 'Chasseur de fantômes',
            ]),
            array_merge($common, [
                'nom_adh' => 'Spengler', 'prenom_adh' => 'Egon',
                'login_adh' => 'egon.spengler', 'email_adh' => 'egon@ghostbusters.nyc',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 6, 'date_crea_adh' => '2024-07-04',
                'ddn_adh' => '1957-11-01', 'lieu_naissance' => 'Cleveland',
                'ville_adh' => 'New York', 'pays_adh' => 'États-Unis', 'cp_adh' => '40072',
                'adresse_adh' => 'Caserne des SOS Fantômes, Hook & Ladder 8, Tribeca',
                'pseudo_adh' => 'print_is_positive', 'prof_adh' => 'Parapsychologue',
            ]),
            array_merge($common, [
                'nom_adh' => 'Stantz', 'prenom_adh' => 'Ray',
                'login_adh' => 'ray.stantz', 'email_adh' => 'ray@ghostbusters.nyc',
                'sexe_adh' => Adherent::MAN, 'titre_adh' => 1,
                'id_statut' => 4, 'date_crea_adh' => '2024-07-04',
                'ddn_adh' => '1959-05-15', 'lieu_naissance' => 'Morrisville',
                'ville_adh' => 'New York', 'pays_adh' => 'États-Unis', 'cp_adh' => '40073',
                'adresse_adh' => 'Caserne des SOS Fantômes, Hook & Ladder 8, Tribeca',
                'pseudo_adh' => 'stantz_occult', 'prof_adh' => 'Chasseur de fantômes / Occultiste',
            ]),
        ];
    }

    /**
     * Get child member data (need parent IDs)
     *
     * @return array<array<string,mixed>>
     */
    private function getChildMemberData(): array
    {
        $common = [
            'mdp_adh' => self::FIXTURE_PASSWORD,
            'mdp_adh2' => self::FIXTURE_PASSWORD,
            'fingerprint' => self::FIXTURE_FINGERPRINT,
            'activite_adh' => true,
            'bool_display_info' => true,
            'pref_lang' => 'fr_FR',
            'id_statut' => 9,
        ];

        return [
            array_merge($common, [
                'nom_adh' => 'Skywalker', 'prenom_adh' => 'Ben',
                'login_adh' => 'ben.skywalker', 'email_adh' => 'ben.skywalker@rebellion.org',
                'sexe_adh' => Adherent::MAN,
                'ddn_adh' => '2005-03-15', 'date_crea_adh' => '2024-01-15',
                'ville_adh' => 'Mos Eisley', 'pays_adh' => 'Tatooine', 'cp_adh' => '00001',
                'adresse_adh' => 'Ferme des Lars', 'pseudo_adh' => 'bensolo',
                'parent_login' => 'luke.skywalker',
            ]),
            array_merge($common, [
                'nom_adh' => 'Solo', 'prenom_adh' => 'Jacen',
                'login_adh' => 'jacen.solo', 'email_adh' => 'jacen.solo@rebellion.org',
                'sexe_adh' => Adherent::MAN,
                'ddn_adh' => '2008-07-20', 'date_crea_adh' => '2024-03-20',
                'ville_adh' => 'Corellia City', 'pays_adh' => 'Corellia', 'cp_adh' => '00003',
                'adresse_adh' => 'Quai d\'amarrage 94', 'pseudo_adh' => 'jacensolo',
                'parent_login' => 'han.solo',
            ]),
            array_merge($common, [
                'nom_adh' => 'Stark', 'prenom_adh' => 'Rickon',
                'login_adh' => 'rickon.stark', 'email_adh' => 'rickon.stark@winterfell.ws',
                'sexe_adh' => Adherent::MAN,
                'ddn_adh' => '2012-06-01', 'date_crea_adh' => '2024-07-22',
                'ville_adh' => 'Winterfell', 'pays_adh' => 'Westeros', 'cp_adh' => '20001',
                'adresse_adh' => 'Château de Winterfell', 'pseudo_adh' => 'rickon',
                'parent_login' => 'arya.stark',
            ]),
            array_merge($common, [
                'nom_adh' => 'Fry', 'prenom_adh' => 'Yancy Jr',
                'login_adh' => 'yancy.fry', 'email_adh' => 'yancy.fry@planetexpress.future',
                'sexe_adh' => Adherent::MAN,
                'ddn_adh' => '2010-11-30', 'date_crea_adh' => '2025-01-10',
                'ville_adh' => 'New New York', 'pays_adh' => 'USA (3000)', 'cp_adh' => '30001',
                'adresse_adh' => 'Planet Express', 'pseudo_adh' => 'yancyjr',
                'parent_login' => 'philip.fry',
            ]),
            array_merge($common, [
                'nom_adh' => 'Doré', 'prenom_adh' => 'Tao',
                'login_adh' => 'tao.dore', 'email_adh' => 'tao@citesdor.soleil',
                'sexe_adh' => Adherent::MAN,
                'ddn_adh' => '2009-04-10', 'date_crea_adh' => '2024-09-01',
                'ville_adh' => 'Cuzco', 'pays_adh' => 'Pérou', 'cp_adh' => '08002',
                'adresse_adh' => 'Temple du Soleil', 'pseudo_adh' => 'tao_inventeur',
                'parent_login' => 'esteban.dore',
            ]),
            array_merge($common, [
                'nom_adh' => 'Anderson', 'prenom_adh' => 'Junior',
                'login_adh' => 'junior.anderson', 'email_adh' => 'junior@matrixreloaded.io',
                'sexe_adh' => Adherent::MAN,
                'ddn_adh' => '2007-01-15', 'date_crea_adh' => '2024-09-01',
                'ville_adh' => 'Mega City One', 'pays_adh' => 'Matrice', 'cp_adh' => '10001',
                'adresse_adh' => 'Appartement 101, tour Metacortex', 'pseudo_adh' => 'neo_jr',
                'parent_login' => 'neo.anderson',
            ]),
            array_merge($common, [
                'nom_adh' => 'Ulysse', 'prenom_adh' => 'Télémaque',
                'login_adh' => 'telemaque.ulysse', 'email_adh' => 'telemaque@olympe.space',
                'sexe_adh' => Adherent::MAN,
                'ddn_adh' => '2010-09-15', 'date_crea_adh' => '2024-04-01',
                'ville_adh' => 'Ithaque Spatiale', 'pays_adh' => 'Grèce Galactique', 'cp_adh' => '31000',
                'adresse_adh' => 'Odysseus, quartier équipage', 'pseudo_adh' => 'telemaque31',
                'parent_login' => 'ulysse',
            ]),
            array_merge($common, [
                'nom_adh' => 'Skellington', 'prenom_adh' => 'Zéro',
                'login_adh' => 'zero.skellington', 'email_adh' => 'zero@halloween-town.spooky',
                'sexe_adh' => Adherent::MAN,
                'ddn_adh' => '2015-10-31', 'date_crea_adh' => '2024-10-31',
                'ville_adh' => 'Halloween Town', 'pays_adh' => 'Mondes de Burton', 'cp_adh' => '66600',
                'adresse_adh' => 'Colline Spirale, chenil fantôme', 'pseudo_adh' => 'zero_ghost',
                'parent_login' => 'jack.skellington',
            ]),
        ];
    }

    /**
     * Get group definitions
     *
     * @return array<array<string,mixed>>
     */
    private function getGroupDefinitions(): array
    {
        return [
            [
                'name' => 'Bureau',
                'managers' => ['leia.organa', 'morpheus'],
                'members' => ['luke.skywalker', 'han.solo', 'daenerys.targaryen', 'turanga.leela', 'jack.skellington'],
            ],
            [
                'name' => 'Le Conseil',
                'parent' => 'Bureau',
                'managers' => ['daenerys.targaryen'],
                'members' => ['arya.stark', 'maestro', 'neo.anderson', 'obiwan.kenobi'],
            ],
            [
                'name' => 'Équipage du Planet Express',
                'managers' => ['turanga.leela'],
                'members' => ['philip.fry', 'bender.rodriguez'],
            ],
            [
                'name' => 'Les Explorateurs Solaires',
                'managers' => ['esteban.dore'],
                'members' => ['zia.dore', 'tao.dore', 'nono', 'ulysse', 'telemaque.ulysse'],
            ],
            [
                'name' => 'Les Âmes Errantes',
                'managers' => ['jack.skellington'],
                'members' => ['sally.ragdoll', 'victor.vandort', 'emily.lamariee'],
            ],
        ];
    }

    /**
     * Get transaction data
     *
     * @return array<array<string,mixed>>
     */
    private function getTransactionData(): array
    {
        return [
            [
                'member_login' => 'leia.organa',
                'trans_amount' => 500.00,
                'trans_desc' => 'Don de la Rébellion',
                'trans_date' => '2024-03-15',
                'type_paiement_trans' => 4,
            ],
            [
                'member_login' => 'morpheus',
                'trans_amount' => 250.00,
                'trans_desc' => 'Collecte de Zion',
                'trans_date' => '2024-06-01',
                'type_paiement_trans' => 6,
            ],
            [
                'member_login' => 'philip.fry',
                'trans_amount' => 100.00,
                'trans_desc' => 'Livraison spéciale',
                'trans_date' => '2025-01-15',
                'type_paiement_trans' => 1,
            ],
            [
                'member_login' => 'daenerys.targaryen',
                'trans_amount' => 1000.00,
                'trans_desc' => 'Trésor de Meereen',
                'trans_date' => '2024-09-10',
                'type_paiement_trans' => 4,
            ],
            [
                'member_login' => 'jack.skellington',
                'trans_amount' => 666.00,
                'trans_desc' => 'Fonds d\'Halloween',
                'trans_date' => '2024-10-31',
                'type_paiement_trans' => 6,
            ],
        ];
    }

    /**
     * Get contribution data
     *
     * @return array<array<string,mixed>>
     */
    private function getContributionData(): array
    {
        $ago = static fn(string $s): string => (new DateTime())->modify("-$s")->format('Y-m-d');
        $from = static fn(string $s): string => (new DateTime())->modify("+$s")->format('Y-m-d');

        return [
            // Luke - 1st expired, 2nd up-to-date
            [
                'member_login' => 'luke.skywalker',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 3,
                'date_debut_cotis' => $ago('25 months'), 'date_fin_cotis' => $ago('13 months'),
                'date_enreg' => $ago('25 months'),
            ],
            [
                'member_login' => 'luke.skywalker',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 3,
                'date_debut_cotis' => $ago('7 months'), 'date_fin_cotis' => $from('5 months'),
                'date_enreg' => $ago('7 months'),
            ],
            // Leia - 1st expired, 2nd up-to-date, linked to transaction
            [
                'member_login' => 'leia.organa',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 4,
                'date_debut_cotis' => $ago('25 months'), 'date_fin_cotis' => $ago('13 months'),
                'date_enreg' => $ago('25 months'),
                'trans_desc' => 'Don de la Rébellion',
            ],
            [
                'member_login' => 'leia.organa',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 4,
                'date_debut_cotis' => $ago('7 months'), 'date_fin_cotis' => $from('5 months'),
                'date_enreg' => $ago('7 months'),
                'trans_desc' => 'Don de la Rébellion',
            ],
            // Han - reduced fee, expired (> 3 months)
            [
                'member_login' => 'han.solo',
                'id_type_cotis' => 2, 'montant_cotis' => 50,
                'type_paiement_cotis' => 1,
                'date_debut_cotis' => $ago('26 months'), 'date_fin_cotis' => $ago('14 months'),
                'date_enreg' => $ago('26 months'),
            ],
            // Obi-Wan - annual fee, expired long ago
            [
                'member_login' => 'obiwan.kenobi',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 4,
                'date_debut_cotis' => $ago('38 months'), 'date_fin_cotis' => $ago('26 months'),
                'date_enreg' => $ago('38 months'),
            ],
            // Neo - annual fee, expired recently (< 3 months)
            [
                'member_login' => 'neo.anderson',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 2,
                'date_debut_cotis' => $ago('14 months'), 'date_fin_cotis' => $ago('2 months'),
                'date_enreg' => $ago('14 months'),
            ],
            // Morpheus - annual fee, expired recently, linked to transaction
            [
                'member_login' => 'morpheus',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 6,
                'date_debut_cotis' => $ago('14 months'), 'date_fin_cotis' => $ago('2 months'),
                'date_enreg' => $ago('14 months'),
                'trans_desc' => 'Collecte de Zion',
            ],
            // Trinity - annual fee, up-to-date
            [
                'member_login' => 'trinity',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 2,
                'date_debut_cotis' => $ago('5 months'), 'date_fin_cotis' => $from('7 months'),
                'date_enreg' => $ago('5 months'),
            ],
            // Arya - reduced fee, expired recently
            [
                'member_login' => 'arya.stark',
                'id_type_cotis' => 2, 'montant_cotis' => 50,
                'type_paiement_cotis' => 1,
                'date_debut_cotis' => $ago('14 months'), 'date_fin_cotis' => $ago('2 months'),
                'date_enreg' => $ago('14 months'),
            ],
            // Daenerys - company fee, expired (> 3 months), linked to transaction
            [
                'member_login' => 'daenerys.targaryen',
                'id_type_cotis' => 3, 'montant_cotis' => 200,
                'type_paiement_cotis' => 4,
                'date_debut_cotis' => $ago('26 months'), 'date_fin_cotis' => $ago('14 months'),
                'date_enreg' => $ago('26 months'),
                'trans_desc' => 'Trésor de Meereen',
            ],
            // Jon - annual fee, up-to-date
            [
                'member_login' => 'jon.snow',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 1,
                'date_debut_cotis' => $ago('3 months'), 'date_fin_cotis' => $from('9 months'),
                'date_enreg' => $ago('3 months'),
            ],
            // Fry - annual fee, up-to-date, linked to transaction
            [
                'member_login' => 'philip.fry',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 1,
                'date_debut_cotis' => $ago('6 months'), 'date_fin_cotis' => $from('6 months'),
                'date_enreg' => $ago('6 months'),
                'trans_desc' => 'Livraison spéciale',
            ],
            // Leela - annual fee, up-to-date
            [
                'member_login' => 'turanga.leela',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 2,
                'date_debut_cotis' => $ago('4 months'), 'date_fin_cotis' => $from('8 months'),
                'date_enreg' => $ago('4 months'),
            ],
            // Bender - donation in kind (no end date)
            [
                'member_login' => 'bender.rodriguez',
                'id_type_cotis' => 4, 'montant_cotis' => 0,
                'type_paiement_cotis' => 6,
                'date_debut_cotis' => $ago('36 months'),
                'date_enreg' => $ago('36 months'),
            ],
            // Esteban - annual fee, expired (> 3 months)
            [
                'member_login' => 'esteban.dore',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 3,
                'date_debut_cotis' => $ago('26 months'), 'date_fin_cotis' => $ago('14 months'),
                'date_enreg' => $ago('26 months'),
            ],
            // Zia - reduced fee, expired (> 3 months)
            [
                'member_login' => 'zia.dore',
                'id_type_cotis' => 2, 'montant_cotis' => 50,
                'type_paiement_cotis' => 3,
                'date_debut_cotis' => $ago('26 months'), 'date_fin_cotis' => $ago('14 months'),
                'date_enreg' => $ago('26 months'),
            ],
            // Maestro - donation in money (no end date)
            [
                'member_login' => 'maestro',
                'id_type_cotis' => 5, 'montant_cotis' => 150,
                'type_paiement_cotis' => 4,
                'date_debut_cotis' => $ago('40 months'),
                'date_enreg' => $ago('40 months'),
            ],
            // Ulysse - annual fee, expired (> 3 months)
            [
                'member_login' => 'ulysse',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 4,
                'date_debut_cotis' => $ago('26 months'), 'date_fin_cotis' => $ago('14 months'),
                'date_enreg' => $ago('26 months'),
            ],
            // Nono - reduced fee, expired (> 3 months)
            [
                'member_login' => 'nono',
                'id_type_cotis' => 2, 'montant_cotis' => 30,
                'type_paiement_cotis' => 1,
                'date_debut_cotis' => $ago('26 months'), 'date_fin_cotis' => $ago('14 months'),
                'date_enreg' => $ago('26 months'),
            ],
            // Jack - annual fee, expired recently, linked to transaction
            [
                'member_login' => 'jack.skellington',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 6,
                'date_debut_cotis' => $ago('14 months'), 'date_fin_cotis' => $ago('2 months'),
                'date_enreg' => $ago('14 months'),
                'trans_desc' => 'Fonds d\'Halloween',
            ],
            // Sally - reduced fee, expired recently
            [
                'member_login' => 'sally.ragdoll',
                'id_type_cotis' => 2, 'montant_cotis' => 50,
                'type_paiement_cotis' => 6,
                'date_debut_cotis' => $ago('14 months'), 'date_fin_cotis' => $ago('2 months'),
                'date_enreg' => $ago('14 months'),
            ],
            // Victor - annual fee, expired (> 3 months)
            [
                'member_login' => 'victor.vandort',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 3,
                'date_debut_cotis' => $ago('26 months'), 'date_fin_cotis' => $ago('14 months'),
                'date_enreg' => $ago('26 months'),
            ],
            // Emily - reduced fee, expired recently
            [
                'member_login' => 'emily.lamariee',
                'id_type_cotis' => 2, 'montant_cotis' => 50,
                'type_paiement_cotis' => 1,
                'date_debut_cotis' => $ago('14 months'), 'date_fin_cotis' => $ago('2 months'),
                'date_enreg' => $ago('14 months'),
            ],
            // Children contributions - all expired ($ago('20 months') / $ago('8 months'))
            // Ben - child reduced fee
            [
                'member_login' => 'ben.skywalker',
                'id_type_cotis' => 2, 'montant_cotis' => 30,
                'type_paiement_cotis' => 3,
                'date_debut_cotis' => $ago('20 months'), 'date_fin_cotis' => $ago('8 months'),
                'date_enreg' => $ago('20 months'),
            ],
            // Jacen - child reduced fee
            [
                'member_login' => 'jacen.solo',
                'id_type_cotis' => 2, 'montant_cotis' => 30,
                'type_paiement_cotis' => 1,
                'date_debut_cotis' => $ago('20 months'), 'date_fin_cotis' => $ago('8 months'),
                'date_enreg' => $ago('20 months'),
            ],
            // Rickon - child reduced fee
            [
                'member_login' => 'rickon.stark',
                'id_type_cotis' => 2, 'montant_cotis' => 30,
                'type_paiement_cotis' => 1,
                'date_debut_cotis' => $ago('20 months'), 'date_fin_cotis' => $ago('8 months'),
                'date_enreg' => $ago('20 months'),
            ],
            // Yancy Jr - child reduced fee
            [
                'member_login' => 'yancy.fry',
                'id_type_cotis' => 2, 'montant_cotis' => 30,
                'type_paiement_cotis' => 1,
                'date_debut_cotis' => $ago('20 months'), 'date_fin_cotis' => $ago('8 months'),
                'date_enreg' => $ago('20 months'),
            ],
            // Tao - child reduced fee
            [
                'member_login' => 'tao.dore',
                'id_type_cotis' => 2, 'montant_cotis' => 30,
                'type_paiement_cotis' => 3,
                'date_debut_cotis' => $ago('20 months'), 'date_fin_cotis' => $ago('8 months'),
                'date_enreg' => $ago('20 months'),
            ],
            // Junior - child reduced fee
            [
                'member_login' => 'junior.anderson',
                'id_type_cotis' => 2, 'montant_cotis' => 30,
                'type_paiement_cotis' => 2,
                'date_debut_cotis' => $ago('20 months'), 'date_fin_cotis' => $ago('8 months'),
                'date_enreg' => $ago('20 months'),
            ],
            // Telemaque - child reduced fee
            [
                'member_login' => 'telemaque.ulysse',
                'id_type_cotis' => 2, 'montant_cotis' => 30,
                'type_paiement_cotis' => 4,
                'date_debut_cotis' => $ago('20 months'), 'date_fin_cotis' => $ago('8 months'),
                'date_enreg' => $ago('20 months'),
            ],
            // Zero - child reduced fee
            [
                'member_login' => 'zero.skellington',
                'id_type_cotis' => 2, 'montant_cotis' => 30,
                'type_paiement_cotis' => 6,
                'date_debut_cotis' => $ago('20 months'), 'date_fin_cotis' => $ago('8 months'),
                'date_enreg' => $ago('20 months'),
            ],
            // New members (20)
            // Hermione - annual fee, up-to-date
            [
                'member_login' => 'hermione.granger',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 3,
                'date_debut_cotis' => $ago('3 months'), 'date_fin_cotis' => $from('9 months'),
                'date_enreg' => $ago('3 months'),
            ],
            // Ron - annual fee, expired recently
            [
                'member_login' => 'ron.weasley',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 1,
                'date_debut_cotis' => $ago('14 months'), 'date_fin_cotis' => $ago('2 months'),
                'date_enreg' => $ago('14 months'),
            ],
            // Albus - annual fee, expired (> 3 months)
            [
                'member_login' => 'albus.dumbledore',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 4,
                'date_debut_cotis' => $ago('26 months'), 'date_fin_cotis' => $ago('14 months'),
                'date_enreg' => $ago('26 months'),
            ],
            // Frodon - annual fee, up-to-date
            [
                'member_login' => 'frodon.sacquet',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 1,
                'date_debut_cotis' => $ago('1 month'), 'date_fin_cotis' => $from('11 months'),
                'date_enreg' => $ago('1 month'),
            ],
            // Gandalf - annual fee, expired recently
            [
                'member_login' => 'gandalf',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 4,
                'date_debut_cotis' => $ago('13 months'), 'date_fin_cotis' => $ago('1 month'),
                'date_enreg' => $ago('13 months'),
            ],
            // Legolas - annual fee, expired (> 3 months)
            [
                'member_login' => 'legolas.sylvain',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 3,
                'date_debut_cotis' => $ago('26 months'), 'date_fin_cotis' => $ago('14 months'),
                'date_enreg' => $ago('26 months'),
            ],
            // Homer - annual fee, expired recently
            [
                'member_login' => 'homer.simpson',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 1,
                'date_debut_cotis' => $ago('14 months'), 'date_fin_cotis' => $ago('2 months'),
                'date_enreg' => $ago('14 months'),
            ],
            // Bart - reduced fee, expired (> 3 months)
            [
                'member_login' => 'bart.simpson',
                'id_type_cotis' => 2, 'montant_cotis' => 30,
                'type_paiement_cotis' => 1,
                'date_debut_cotis' => $ago('20 months'), 'date_fin_cotis' => $ago('8 months'),
                'date_enreg' => $ago('20 months'),
            ],
            // Lisa - reduced fee, expired (> 3 months)
            [
                'member_login' => 'lisa.simpson',
                'id_type_cotis' => 2, 'montant_cotis' => 30,
                'type_paiement_cotis' => 3,
                'date_debut_cotis' => $ago('20 months'), 'date_fin_cotis' => $ago('8 months'),
                'date_enreg' => $ago('20 months'),
            ],
            // Astérix - annual fee, up-to-date
            [
                'member_login' => 'asterix',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 1,
                'date_debut_cotis' => $ago('5 months'), 'date_fin_cotis' => $from('7 months'),
                'date_enreg' => $ago('5 months'),
            ],
            // Obélix - annual fee, expired
            [
                'member_login' => 'obelix',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 1,
                'date_debut_cotis' => $ago('26 months'), 'date_fin_cotis' => $ago('14 months'),
                'date_enreg' => $ago('26 months'),
            ],
            // Panoramix - annual fee, up-to-date
            [
                'member_login' => 'panoramix',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 3,
                'date_debut_cotis' => $ago('1 month'), 'date_fin_cotis' => $from('11 months'),
                'date_enreg' => $ago('1 month'),
            ],
            // Arthur Pendragon - annual fee, expired recently
            [
                'member_login' => 'arthur.pendragon',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 4,
                'date_debut_cotis' => $ago('14 months'), 'date_fin_cotis' => $ago('2 months'),
                'date_enreg' => $ago('14 months'),
            ],
            // Perceval - annual fee, up-to-date
            [
                'member_login' => 'perceval.gallois',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 1,
                'date_debut_cotis' => $ago('3 months'), 'date_fin_cotis' => $from('9 months'),
                'date_enreg' => $ago('3 months'),
            ],
            // Léodagan - annual fee, expired
            [
                'member_login' => 'leodagan.lancelot',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 4,
                'date_debut_cotis' => $ago('25 months'), 'date_fin_cotis' => $ago('13 months'),
                'date_enreg' => $ago('25 months'),
            ],
            // Marty - annual fee, up-to-date
            [
                'member_login' => 'marty.mcfly',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 1,
                'date_debut_cotis' => $ago('5 months'), 'date_fin_cotis' => $from('7 months'),
                'date_enreg' => $ago('5 months'),
            ],
            // Doc - annual fee, expired (> 3 months)
            [
                'member_login' => 'doc.brown',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 3,
                'date_debut_cotis' => $ago('25 months'), 'date_fin_cotis' => $ago('13 months'),
                'date_enreg' => $ago('25 months'),
            ],
            // Inspecteur Gadget - annual fee, expired recently
            [
                'member_login' => 'inspecteur.gadget',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 6,
                'date_debut_cotis' => $ago('15 months'), 'date_fin_cotis' => $ago('3 months'),
                'date_enreg' => $ago('15 months'),
            ],
            // Penny - reduced fee, expired (> 3 months)
            [
                'member_login' => 'penny.parker',
                'id_type_cotis' => 2, 'montant_cotis' => 30,
                'type_paiement_cotis' => 6,
                'date_debut_cotis' => $ago('20 months'), 'date_fin_cotis' => $ago('8 months'),
                'date_enreg' => $ago('20 months'),
            ],
            // Fox - annual fee, up-to-date
            [
                'member_login' => 'fox.mulder',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 4,
                'date_debut_cotis' => $ago('4 months'), 'date_fin_cotis' => $from('8 months'),
                'date_enreg' => $ago('4 months'),
            ],
            // Dana - annual fee, expired (> 3 months)
            [
                'member_login' => 'dana.scully',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 4,
                'date_debut_cotis' => $ago('26 months'), 'date_fin_cotis' => $ago('14 months'),
                'date_enreg' => $ago('26 months'),
            ],
            // Peter - annual fee, up-to-date
            [
                'member_login' => 'peter.venkman',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 1,
                'date_debut_cotis' => $ago('6 months'), 'date_fin_cotis' => $from('6 months'),
                'date_enreg' => $ago('6 months'),
            ],
            // Egon - annual fee, expired (> 3 months)
            [
                'member_login' => 'egon.spengler',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 3,
                'date_debut_cotis' => $ago('26 months'), 'date_fin_cotis' => $ago('14 months'),
                'date_enreg' => $ago('26 months'),
            ],
            // Ray - annual fee, expired recently
            [
                'member_login' => 'ray.stantz',
                'id_type_cotis' => 1, 'montant_cotis' => 92,
                'type_paiement_cotis' => 2,
                'date_debut_cotis' => $ago('14 months'), 'date_fin_cotis' => $ago('2 months'),
                'date_enreg' => $ago('14 months'),
            ],
        ];
    }

    /**
     * Get dynamic field values for members
     *
     * @return array<string, array<string, string|int>>
     */
    private function getMemberDynamicValues(): array
    {
        return [
            'luke.skywalker' => [
                'Univers fictif d\'origine' => 0,
                'Niveau de la Force (midichlorians)' => '14000',
                'Phrase culte' => 'Je suis un Jedi, comme mon père avant moi.',
                'Date d\'entrée au conseil galactique' => '2024-01-15',
                'Membre du Conseil Jedi' => 1,
            ],
            'leia.organa' => [
                'Univers fictif d\'origine' => 0,
                'Niveau de la Force (midichlorians)' => '13000',
                'Phrase culte' => 'Vous êtes notre seul espoir.',
                'Date d\'entrée au conseil galactique' => '2023-06-10',
                'Membre du Conseil Jedi' => 1,
            ],
            'han.solo' => [
                'Univers fictif d\'origine' => 0,
                'Niveau de la Force (midichlorians)' => '50',
                'Phrase culte' => 'Je sais.',
                'Date d\'entrée au conseil galactique' => '2024-03-20',
                'Membre du Conseil Jedi' => 0,
            ],
            'obiwan.kenobi' => [
                'Univers fictif d\'origine' => 0,
                'Niveau de la Force (midichlorians)' => '15000',
                'Phrase culte' => 'Que la Force soit avec toi.',
                'Date d\'entrée au conseil galactique' => '2023-01-01',
                'Membre du Conseil Jedi' => 1,
            ],
            'neo.anderson' => [
                'Univers fictif d\'origine' => 1,
                'Niveau de la Force (midichlorians)' => '99999',
                'Phrase culte' => 'Il n\'y a pas de cuillère.',
                'Date d\'entrée au conseil galactique' => '2023-09-01',
                'Membre du Conseil Jedi' => 0,
            ],
            'morpheus' => [
                'Univers fictif d\'origine' => 1,
                'Niveau de la Force (midichlorians)' => '8000',
                'Phrase culte' => 'Pilule rouge ou pilule bleue ?',
                'Date d\'entrée au conseil galactique' => '2023-09-01',
                'Membre du Conseil Jedi' => 0,
            ],
            'trinity' => [
                'Univers fictif d\'origine' => 1,
                'Niveau de la Force (midichlorians)' => '7500',
                'Phrase culte' => 'Esquive ça.',
                'Date d\'entrée au conseil galactique' => '2024-02-14',
                'Membre du Conseil Jedi' => 0,
            ],
            'arya.stark' => [
                'Univers fictif d\'origine' => 2,
                'Niveau de la Force (midichlorians)' => '100',
                'Phrase culte' => 'Valar Morghulis.',
                'Date d\'entrée au conseil galactique' => '2024-07-22',
                'Membre du Conseil Jedi' => 0,
            ],
            'daenerys.targaryen' => [
                'Univers fictif d\'origine' => 2,
                'Niveau de la Force (midichlorians)' => '200',
                'Phrase culte' => 'Dracarys !',
                'Date d\'entrée au conseil galactique' => '2023-11-05',
                'Membre du Conseil Jedi' => 0,
            ],
            'jon.snow' => [
                'Univers fictif d\'origine' => 2,
                'Niveau de la Force (midichlorians)' => '150',
                'Phrase culte' => 'Je ne sais rien.',
                'Date d\'entrée au conseil galactique' => '2024-08-01',
                'Membre du Conseil Jedi' => 0,
            ],
            'philip.fry' => [
                'Univers fictif d\'origine' => 3,
                'Niveau de la Force (midichlorians)' => '1',
                'Phrase culte' => 'Ferme-la et prends mon argent !',
                'Date d\'entrée au conseil galactique' => '2025-01-10',
                'Membre du Conseil Jedi' => 0,
            ],
            'turanga.leela' => [
                'Univers fictif d\'origine' => 3,
                'Niveau de la Force (midichlorians)' => '300',
                'Phrase culte' => 'C\'est techniquement correct, le meilleur type de correct.',
                'Date d\'entrée au conseil galactique' => '2024-05-15',
                'Membre du Conseil Jedi' => 0,
            ],
            'bender.rodriguez' => [
                'Univers fictif d\'origine' => 3,
                'Niveau de la Force (midichlorians)' => '0',
                'Phrase culte' => 'Embrassez mon brillant postérieur métallique !',
                'Date d\'entrée au conseil galactique' => '2023-04-01',
                'Membre du Conseil Jedi' => 0,
            ],
            'esteban.dore' => [
                'Univers fictif d\'origine' => 4,
                'Niveau de la Force (midichlorians)' => '500',
                'Phrase culte' => 'Enfant du Soleil, tu parcours la Terre et le Ciel !',
                'Date d\'entrée au conseil galactique' => '2024-09-01',
                'Membre du Conseil Jedi' => 0,
            ],
            'zia.dore' => [
                'Univers fictif d\'origine' => 4,
                'Niveau de la Force (midichlorians)' => '450',
                'Phrase culte' => 'Esteban, fais attention !',
                'Date d\'entrée au conseil galactique' => '2024-09-01',
                'Membre du Conseil Jedi' => 0,
            ],
            'maestro' => [
                'Univers fictif d\'origine' => 5,
                'Niveau de la Force (midichlorians)' => '42',
                'Phrase culte' => 'Mes amis, il était une fois...',
                'Date d\'entrée au conseil galactique' => '2023-01-01',
                'Membre du Conseil Jedi' => 1,
            ],
            'ulysse' => [
                'Univers fictif d\'origine' => 6,
                'Niveau de la Force (midichlorians)' => '3100',
                'Phrase culte' => 'Nono, en avant !',
                'Date d\'entrée au conseil galactique' => '2024-04-01',
                'Membre du Conseil Jedi' => 0,
            ],
            'nono' => [
                'Univers fictif d\'origine' => 6,
                'Niveau de la Force (midichlorians)' => '100',
                'Phrase culte' => 'Oui oui oui, Ulysse !',
                'Date d\'entrée au conseil galactique' => '2024-04-01',
                'Membre du Conseil Jedi' => 0,
            ],
            'jack.skellington' => [
                'Univers fictif d\'origine' => 7,
                'Niveau de la Force (midichlorians)' => '666',
                'Phrase culte' => 'Qu\'est-ce que c\'est ? Qu\'est-ce que c\'est ?',
                'Date d\'entrée au conseil galactique' => '2023-10-31',
                'Membre du Conseil Jedi' => 0,
            ],
            'sally.ragdoll' => [
                'Univers fictif d\'origine' => 7,
                'Niveau de la Force (midichlorians)' => '333',
                'Phrase culte' => 'J\'ai un mauvais pressentiment...',
                'Date d\'entrée au conseil galactique' => '2024-01-01',
                'Membre du Conseil Jedi' => 0,
            ],
            'victor.vandort' => [
                'Univers fictif d\'origine' => 7,
                'Niveau de la Force (midichlorians)' => '100',
                'Phrase culte' => 'Avec cette main, je tiendrai tes rêves...',
                'Date d\'entrée au conseil galactique' => '2024-06-15',
                'Membre du Conseil Jedi' => 0,
            ],
            'emily.lamariee' => [
                'Univers fictif d\'origine' => 7,
                'Niveau de la Force (midichlorians)' => '200',
                'Phrase culte' => 'Tu peux embrasser la mariée.',
                'Date d\'entrée au conseil galactique' => '2024-06-15',
                'Membre du Conseil Jedi' => 0,
            ],
            // Children - dynamic values
            'ben.skywalker' => [
                'Univers fictif d\'origine' => 0,
                'Niveau de la Force (midichlorians)' => '12000',
                'Phrase culte' => 'Oncle Luke, enseigne-moi !',
                'Date d\'entrée au conseil galactique' => '2024-01-15',
                'Membre du Conseil Jedi' => 0,
            ],
            'jacen.solo' => [
                'Univers fictif d\'origine' => 0,
                'Niveau de la Force (midichlorians)' => '11500',
                'Phrase culte' => 'Je piloterai le Faucon Millénium !',
                'Date d\'entrée au conseil galactique' => '2024-03-20',
                'Membre du Conseil Jedi' => 0,
            ],
            'rickon.stark' => [
                'Univers fictif d\'origine' => 2,
                'Niveau de la Force (midichlorians)' => '75',
                'Phrase culte' => 'Les loups-garous du Nord!',
                'Date d\'entrée au conseil galactique' => '2024-07-22',
                'Membre du Conseil Jedi' => 0,
            ],
            'yancy.fry' => [
                'Univers fictif d\'origine' => 3,
                'Niveau de la Force (midichlorians)' => '2',
                'Phrase culte' => 'Papa, tu es un livreur ?',
                'Date d\'entrée au conseil galactique' => '2025-01-10',
                'Membre du Conseil Jedi' => 0,
            ],
            'tao.dore' => [
                'Univers fictif d\'origine' => 4,
                'Niveau de la Force (midichlorians)' => '400',
                'Phrase culte' => 'J\'ai inventé un appareil fantastique!',
                'Date d\'entrée au conseil galactique' => '2024-09-01',
                'Membre du Conseil Jedi' => 0,
            ],
            'junior.anderson' => [
                'Univers fictif d\'origine' => 1,
                'Niveau de la Force (midichlorians)' => '90000',
                'Phrase culte' => 'Papa, est-ce que tu es l\'Élu ?',
                'Date d\'entrée au conseil galactique' => '2024-09-01',
                'Membre du Conseil Jedi' => 0,
            ],
            'telemaque.ulysse' => [
                'Univers fictif d\'origine' => 6,
                'Niveau de la Force (midichlorians)' => '2800',
                'Phrase culte' => 'Père, je viens avec toi aux confins de l\'univers.',
                'Date d\'entrée au conseil galactique' => '2024-04-01',
                'Membre du Conseil Jedi' => 0,
            ],
            'zero.skellington' => [
                'Univers fictif d\'origine' => 7,
                'Niveau de la Force (midichlorians)' => '500',
                'Phrase culte' => 'Ouaf ! Ouaf ! Ouaf !',
                'Date d\'entrée au conseil galactique' => '2024-10-31',
                'Membre du Conseil Jedi' => 0,
            ],
            // Harry Potter
            'hermione.granger' => [
                'Univers fictif d\'origine' => 8,
                'Niveau de la Force (midichlorians)' => '9500',
                'Phrase culte' => 'C\'est dans les livres, vous savez.',
                'Date d\'entrée au conseil galactique' => '2023-09-01',
                'Membre du Conseil Jedi' => 1,
            ],
            'ron.weasley' => [
                'Univers fictif d\'origine' => 8,
                'Niveau de la Force (midichlorians)' => '7500',
                'Phrase culte' => 'Sacré nom d\'une sorcière boiteuse !',
                'Date d\'entrée au conseil galactique' => '2023-09-01',
                'Membre du Conseil Jedi' => 0,
            ],
            'albus.dumbledore' => [
                'Univers fictif d\'origine' => 8,
                'Niveau de la Force (midichlorians)' => '99000',
                'Phrase culte' => 'Le bonheur peut se trouver même dans les moments les plus sombres.',
                'Date d\'entrée au conseil galactique' => '2023-01-01',
                'Membre du Conseil Jedi' => 1,
            ],
            // Le Seigneur des Anneaux
            'frodon.sacquet' => [
                'Univers fictif d\'origine' => 9,
                'Niveau de la Force (midichlorians)' => '300',
                'Phrase culte' => 'Je porterai l\'Anneau, bien que je ne sache pas le chemin.',
                'Date d\'entrée au conseil galactique' => '2024-03-01',
                'Membre du Conseil Jedi' => 0,
            ],
            'gandalf' => [
                'Univers fictif d\'origine' => 9,
                'Niveau de la Force (midichlorians)' => '88000',
                'Phrase culte' => 'Tu ne passeras pas !',
                'Date d\'entrée au conseil galactique' => '2023-01-15',
                'Membre du Conseil Jedi' => 1,
            ],
            'legolas.sylvain' => [
                'Univers fictif d\'origine' => 9,
                'Niveau de la Force (midichlorians)' => '5000',
                'Phrase culte' => 'Et mon arc !',
                'Date d\'entrée au conseil galactique' => '2024-03-01',
                'Membre du Conseil Jedi' => 0,
            ],
            // Les Simpsons
            'homer.simpson' => [
                'Univers fictif d\'origine' => 10,
                'Niveau de la Force (midichlorians)' => '42',
                'Phrase culte' => 'D\'oh !',
                'Date d\'entrée au conseil galactique' => '2024-05-01',
                'Membre du Conseil Jedi' => 0,
            ],
            'bart.simpson' => [
                'Univers fictif d\'origine' => 10,
                'Niveau de la Force (midichlorians)' => '150',
                'Phrase culte' => 'Cowabunga mec !',
                'Date d\'entrée au conseil galactique' => '2024-05-01',
                'Membre du Conseil Jedi' => 0,
            ],
            'lisa.simpson' => [
                'Univers fictif d\'origine' => 10,
                'Niveau de la Force (midichlorians)' => '4200',
                'Phrase culte' => 'Je joue du saxophone, donc je pense.',
                'Date d\'entrée au conseil galactique' => '2024-05-01',
                'Membre du Conseil Jedi' => 0,
            ],
            // Astérix
            'asterix' => [
                'Univers fictif d\'origine' => 11,
                'Niveau de la Force (midichlorians)' => '200',
                'Phrase culte' => 'Tututut... Tututut...',
                'Date d\'entrée au conseil galactique' => '2024-06-01',
                'Membre du Conseil Jedi' => 0,
            ],
            'obelix' => [
                'Univers fictif d\'origine' => 11,
                'Niveau de la Force (midichlorians)' => '500',
                'Phrase culte' => 'Ils sont fous ces Romains !',
                'Date d\'entrée au conseil galactique' => '2024-06-01',
                'Membre du Conseil Jedi' => 0,
            ],
            'panoramix' => [
                'Univers fictif d\'origine' => 11,
                'Niveau de la Force (midichlorians)' => '8000',
                'Phrase culte' => 'Voilà ce qui s\'appelle un bain de pied !',
                'Date d\'entrée au conseil galactique' => '2024-06-01',
                'Membre du Conseil Jedi' => 1,
            ],
            // Kaamelott
            'arthur.pendragon' => [
                'Univers fictif d\'origine' => 16,
                'Niveau de la Force (midichlorians)' => '1000',
                'Phrase culte' => 'C\'est un peu beaucoup là, beaucoup trop...',
                'Date d\'entrée au conseil galactique' => '2024-07-15',
                'Membre du Conseil Jedi' => 1,
            ],
            'perceval.gallois' => [
                'Univers fictif d\'origine' => 16,
                'Niveau de la Force (midichlorians)' => '100',
                'Phrase culte' => 'On n\'a pas le même vocabulaire.',
                'Date d\'entrée au conseil galactique' => '2024-07-15',
                'Membre du Conseil Jedi' => 0,
            ],
            'leodagan.lancelot' => [
                'Univers fictif d\'origine' => 16,
                'Niveau de la Force (midichlorians)' => '1200',
                'Phrase culte' => 'Faut pas chercher à comprendre.',
                'Date d\'entrée au conseil galactique' => '2024-07-15',
                'Membre du Conseil Jedi' => 0,
            ],
            // Retour vers le futur
            'marty.mcfly' => [
                'Univers fictif d\'origine' => 12,
                'Niveau de la Force (midichlorians)' => '1985',
                'Phrase culte' => 'Ça va pas, non ?',
                'Date d\'entrée au conseil galactique' => '2024-10-26',
                'Membre du Conseil Jedi' => 0,
            ],
            'doc.brown' => [
                'Univers fictif d\'origine' => 12,
                'Niveau de la Force (midichlorians)' => '2015',
                'Phrase culte' => 'Grand Scott !',
                'Date d\'entrée au conseil galactique' => '2024-10-26',
                'Membre du Conseil Jedi' => 0,
            ],
            // Inspecteur Gadget
            'inspecteur.gadget' => [
                'Univers fictif d\'origine' => 13,
                'Niveau de la Force (midichlorians)' => '10',
                'Phrase culte' => 'Go-go gadget !',
                'Date d\'entrée au conseil galactique' => '2023-09-01',
                'Membre du Conseil Jedi' => 0,
            ],
            'penny.parker' => [
                'Univers fictif d\'origine' => 13,
                'Niveau de la Force (midichlorians)' => '2500',
                'Phrase culte' => 'Mon ordinateur de poche a tout calculé.',
                'Date d\'entrée au conseil galactique' => '2023-09-01',
                'Membre du Conseil Jedi' => 0,
            ],
            // X-Files
            'fox.mulder' => [
                'Univers fictif d\'origine' => 14,
                'Niveau de la Force (midichlorians)' => '1013',
                'Phrase culte' => 'La vérité est ailleurs.',
                'Date d\'entrée au conseil galactique' => '2024-01-10',
                'Membre du Conseil Jedi' => 0,
            ],
            'dana.scully' => [
                'Univers fictif d\'origine' => 14,
                'Niveau de la Force (midichlorians)' => '1013',
                'Phrase culte' => 'Il doit y avoir une explication rationnelle.',
                'Date d\'entrée au conseil galactique' => '2024-01-10',
                'Membre du Conseil Jedi' => 0,
            ],
            // Ghostbusters
            'peter.venkman' => [
                'Univers fictif d\'origine' => 15,
                'Niveau de la Force (midichlorians)' => '1984',
                'Phrase culte' => 'Back off man, je suis scientifique.',
                'Date d\'entrée au conseil galactique' => '2024-07-04',
                'Membre du Conseil Jedi' => 0,
            ],
            'egon.spengler' => [
                'Univers fictif d\'origine' => 15,
                'Niveau de la Force (midichlorians)' => '9000',
                'Phrase culte' => 'Ne protonisez jamais les flux.',
                'Date d\'entrée au conseil galactique' => '2024-07-04',
                'Membre du Conseil Jedi' => 1,
            ],
            'ray.stantz' => [
                'Univers fictif d\'origine' => 15,
                'Niveau de la Force (midichlorians)' => '7777',
                'Phrase culte' => 'J\'ai rêvé de Gozer le Gozerien.',
                'Date d\'entrée au conseil galactique' => '2024-07-04',
                'Membre du Conseil Jedi' => 0,
            ],
        ];
    }
}
