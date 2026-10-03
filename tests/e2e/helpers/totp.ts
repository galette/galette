/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import crypto from 'node:crypto';

/**
 * TOTP (RFC 6238), implemented here rather than driven through Galette.
 *
 * A code produced by the code under test would only prove that implementation
 * agrees with itself. Producing it independently is what shows an authenticator
 * application will work.
 */

const PERIOD = 30;
const DIGITS = 6;
const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

/**
 * Decode a base32 secret, the encoding every authenticator expects
 *
 * @param secret Base32 secret, as shown on the enrolment page
 */
function base32Decode(secret: string): Buffer {
  let bits = '';
  for (const char of secret.replace(/=+$/, '').toUpperCase()) {
    const value = BASE32_ALPHABET.indexOf(char);
    if (value === -1) {
      throw new Error(`Not a base32 character: ${char}`);
    }
    bits += value.toString(2).padStart(5, '0');
  }

  const bytes: number[] = [];
  for (let i = 0; i + 8 <= bits.length; i += 8) {
    bytes.push(parseInt(bits.slice(i, i + 8), 2));
  }
  return Buffer.from(bytes);
}

/**
 * Which time slice an instant falls in. Codes are single use per slice, so a
 * test needing a fresh code has to move to the next one.
 *
 * @param at Instant in milliseconds, now by default
 */
export function timeSlice(at: number = Date.now()): number {
  return Math.floor(at / 1000 / PERIOD);
}

/**
 * The code valid at a given instant
 *
 * @param secret Base32 shared secret
 * @param at     Instant in milliseconds, now by default
 */
export function totp(secret: string, at: number = Date.now()): string {
  const counter = Buffer.alloc(8);
  counter.writeBigUInt64BE(BigInt(timeSlice(at)));

  const digest = crypto.createHmac('sha1', base32Decode(secret)).update(counter).digest();
  const offset = digest[digest.length - 1] & 0x0f;
  const value = digest.readUInt32BE(offset) & 0x7fffffff;

  return String(value % 10 ** DIGITS).padStart(DIGITS, '0');
}

/**
 * A code from the next period.
 *
 * Galette accepts one period either side, and remembers the last slice it
 * accepted so a code cannot be replayed. After one code has been spent, the
 * next slice is therefore the way to a fresh, acceptable one -- without waiting
 * thirty seconds.
 *
 * @param secret Base32 shared secret
 */
export function nextTotp(secret: string): string {
  return totp(secret, Date.now() + PERIOD * 1000);
}
