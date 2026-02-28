export interface Product {
    id: number;
    name: string;
    description: string | null;
    image_url: string | null;
    download_url: string | null;
    version: string;
    status: number;
    price: string;
    type: string;
    tutorial_link: string | null;
    file_name: string | null;
    updated_at: string;
    created_at: string;
}
