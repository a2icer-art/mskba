# Шаг 1
План сущностей ролей участников (в домене Participants).

Предлагаемая связь:
- Базовая связь с ролью: `participant_role_assignments` (user_id + participant_role_id).
- Роль-специфичная сущность хранит FK `participant_role_assignment_id` (one-to-one), чтобы поддерживать несколько ролей у одного пользователя.

Сущности и предварительные поля:
- PlayerProfile: height_cm, weight_kg, position, dominant_hand, experience_from (date), skills (json), achievements (text), rating (int), bio (text).
- CoachProfile: experience_from (date), specialization, certifications (text/json), regalia (text), teams (text/json).
- RefereeProfile: category, experience_from (date), certifications (text/json), matches_count, regalia (text).
- VenueAdminProfile: experience_from (date), venue_management (text), certifications (text/json).
- MediaProfile: experience_from (date), specialization, portfolio_url, channels (json).
- SellerProfile: experience_from (date), products (text/json), company (string), contacts (json).
- StaffProfile: experience_from (date), specialization, certifications (text/json), regalia (text).
- OtherProfile: experience_from (date), description (text).

Общие поля для всех: created_by, updated_by, deleted_by, deleted_at (soft delete), статус (если потребуется), commentaries.
