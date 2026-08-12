export interface MenuItem {
  id: string;
  name: string;
  description: string;
  price: string;
  category: 'dimsum' | 'main' | 'soup' | 'dessert';
  spicy?: boolean;
  vegetarian?: boolean;
  glutenFree?: boolean;
}

export interface GalleryImage {
  id: string;
  src: string;
  alt: string;
}