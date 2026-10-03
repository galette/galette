<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\IO;

/**
 * Member card PDF adaptative
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 * @author Fabrice Santoni <fabrice@santoni.ch>
 */
class PdfMembersCardsAdaptative extends PdfMembersCards
{
    protected float $ratio;
    protected float $wphoto;
    protected float $hphoto;
    protected string $adh_nbr;
    protected float $cell_he;
    protected int $ban_max_he;
    protected float $email_y;
    protected int $max_text_size_full;
    protected int $max_text_size_top;
    protected int $max_text_size_center;
}
