<?php

// SMS templates (business-rules §5).

return [
    'absent' => 'Dear guardian, your child :name (roll :roll) is absent from school today, :date. — :school',
    'due' => 'Fees due for :name: Tk :amount. Please pay by :due_date. — :school',
    'result' => ':name got GPA :gpa in :exam. — :school',
    'notice' => ':title: :body — :school',
    'notice_title_only' => ':title — :school',
];
