-- Additional schema for ticket attachments and canned responses

CREATE TABLE ticket_attachments (
    id SERIAL PRIMARY KEY,
    ticket_comment_id INTEGER NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INTEGER NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT NOW(),
    FOREIGN KEY (ticket_comment_id) REFERENCES ticket_comments(id) ON DELETE CASCADE
);

CREATE TABLE canned_responses (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    response_text TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Seed some canned responses
INSERT INTO canned_responses (title, response_text) VALUES
('Greeting', 'Hello,

Thank you for contacting support.
'),
('Closing Ticket', 'We are closing this ticket for now. If you have any other questions, please feel free to open a new ticket.

Thank you,'),
('Request for More Information', 'Could you please provide us with more information regarding this issue? Specifically, we need to know...
');

