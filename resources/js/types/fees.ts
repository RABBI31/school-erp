export type FeeHeadType = 'monthly' | 'one_time' | 'yearly';
export type WaiverType = 'percent' | 'fixed';
export type InvoiceStatus = 'unpaid' | 'partial' | 'paid' | 'cancelled';
export type PaymentMethod = 'cash' | 'bkash' | 'nagad' | 'bank' | 'card';

/** Money arrives as a decimal string, e.g. "1250.00". */
export type Money = string;

export interface FeeHead {
    id: number;
    name_en: string;
    name_bn: string;
    type: FeeHeadType;
}

export interface FeeWaiver {
    id: number;
    enrollment_id: number;
    fee_head_id: number;
    type: WaiverType;
    value: Money;
    reason: string | null;
    fee_head: Pick<FeeHead, 'id' | 'name_en' | 'name_bn'>;
}

export interface SectionOption {
    id: number;
    name: string;
    school_class_id: number;
    class_name_en: string;
    class_name_bn: string;
}

export interface ClassOption {
    id: number;
    name_en: string;
    name_bn: string;
}

/** A student as the fee screens show them. */
export interface FeeStudent {
    enrollment_id: number;
    student_id: number;
    student_uid: string;
    name_en: string;
    name_bn: string | null;
    class_name_en: string;
    class_name_bn: string;
    section: string;
    roll: number;
}

export interface InvoiceRow {
    id: number;
    invoice_no: string;
    month: string | null;
    due_date: string;
    subtotal: Money;
    waiver_total: Money;
    total: Money;
    paid_total: Money;
    status: InvoiceStatus;
    enrollment: {
        id: number;
        roll: number;
        student: { id: number; student_uid: string; name_en: string; name_bn: string | null };
        section: { id: number; name: string; school_class: ClassOption };
    };
}

export interface CollectStudent extends FeeStudent {
    guardian_phone: string | null;
    due: Money;
    invoices: {
        id: number;
        invoice_no: string;
        month: string | null;
        due_date: string;
        total: Money;
        paid_total: Money;
        due: Money;
        status: InvoiceStatus;
        heads: { name_en: string; name_bn: string; amount: Money }[];
    }[];
    payments: { id: number; receipt_no: string; paid_at: string; amount: Money; method: PaymentMethod }[];
}

export interface DailyPayment extends Omit<FeeStudent, 'enrollment_id' | 'student_uid'> {
    id: number;
    receipt_no: string;
    time: string;
    amount: Money;
    method: PaymentMethod;
    transaction_ref: string | null;
    received_by: string | null;
    cancelled: boolean;
    cancel_reason: string | null;
    cancelled_by: string | null;
}

export interface DueRow extends FeeStudent {
    guardian_phone: string | null;
    due: Money;
    invoice_count: number;
    since: string | null;
    last_due_date: string;
}

export interface MonthlyClassRow extends ClassOption {
    invoices: number;
    paid_invoices: number;
    subtotal: Money;
    waived: Money;
    total: Money;
    paid: Money;
    due: Money;
}
