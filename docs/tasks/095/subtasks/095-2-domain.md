# 095-2-domain

Цель: определить хранение, правила и формулу комиссии супервайзера.

- Хранение: `venue_settings.supervisor_fee_percent`, `venue_settings.supervisor_fee_amount_rub`, `venue_settings.supervisor_fee_is_fixed`.
- Применение: только при активном контракте `supervisor` для площадки.
- Формула:
  - проценты: `fee = round(base_amount * percent / 100)`;
  - фикс: `fee = fixed_amount`;
  - `total = base_amount + fee`.
- В `payments.meta` фиксируются `base_amount_minor`, `supervisor_fee_percent`, `supervisor_fee_amount_minor`, `supervisor_fee_is_fixed`, `total_amount_minor`.
