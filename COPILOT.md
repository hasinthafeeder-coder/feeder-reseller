# Feeder Reseller Application

This application is ONLY responsible for reseller users.

## Architecture

- This project shares models through the feeder-core Composer package.
- Database migrations are managed only by the dropshipping-db project.
- Never generate migrations in this repository.
- Never duplicate models that already exist in feeder-core.
- Use services for business logic.
- Keep controllers thin.
- Use Form Requests for validation.
- Uploaded files are stored through the shared file server.
- User registration is a multi-step wizard.
- Registration progress is saved after every step.
- The users table stores partially completed registrations with status = REGISTERING.
- Login is blocked until the user's status becomes APPROVED.
- OTP verification currently stores OTP records in the database only.
- Do not implement SMS gateway code.
- Do not generate database schema unless explicitly requested.
