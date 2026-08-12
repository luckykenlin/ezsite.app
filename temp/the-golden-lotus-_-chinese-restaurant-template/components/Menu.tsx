import React, { useState, useRef } from 'react';
import { MenuItem } from '../types';
import { Flame, Leaf, Star, WheatOff } from 'lucide-react';

interface MenuProps {
  bannerVisible?: boolean;
}

// Expanded Menu Data (approx 50 items)
const MENU_ITEMS: MenuItem[] = [
  // --- Dim Sum ---
  { id: 'ds1', name: 'Har Gow', description: 'Steamed crystal shrimp dumplings with bamboo shoots.', price: '$8', category: 'dimsum', glutenFree: true },
  { id: 'ds2', name: 'Siu Mai', description: 'Open-topped steamed pork and shrimp dumplings topped with roe.', price: '$8', category: 'dimsum' },
  { id: 'ds3', name: 'Char Siu Bao', description: 'Fluffy steamed buns filled with honey BBQ pork.', price: '$7', category: 'dimsum' },
  { id: 'ds4', name: 'Xiao Long Bao', description: 'Shanghai soup dumplings filled with pork and rich broth.', price: '$9', category: 'dimsum' },
  { id: 'ds5', name: 'Feng Zhua', description: 'Braised chicken feet in black bean sauce.', price: '$7', category: 'dimsum', spicy: true },
  { id: 'ds6', name: 'Cheung Fun (Shrimp)', description: 'Steamed rice noodle rolls filled with fresh shrimp.', price: '$9', category: 'dimsum', glutenFree: true },
  { id: 'ds7', name: 'Cheung Fun (Beef)', description: 'Steamed rice noodle rolls filled with minced beef and cilantro.', price: '$9', category: 'dimsum', glutenFree: true },
  { id: 'ds8', name: 'Lo Mai Gai', description: 'Sticky rice with chicken and mushroom wrapped in lotus leaf.', price: '$8', category: 'dimsum', glutenFree: true },
  { id: 'ds9', name: 'Turnip Cake', description: 'Pan-fried radish cake with dried shrimp and sausage.', price: '$7', category: 'dimsum' },
  { id: 'ds10', name: 'Fried Taro Dumpling', description: 'Crispy taro mash filled with savory pork.', price: '$7', category: 'dimsum', glutenFree: true },
  { id: 'ds11', name: 'Egg Yolk Bun', description: 'Steamed bun with flowing salted egg yolk custard.', price: '$7', category: 'dimsum', vegetarian: true },
  { id: 'ds12', name: 'Potstickers', description: 'Pan-fried pork and cabbage dumplings.', price: '$8', category: 'dimsum' },
  { id: 'ds13', name: 'Scallion Pancakes', description: 'Crispy, flaky unleavened flatbread with green onions.', price: '$6', category: 'dimsum', vegetarian: true },

  // --- Soup ---
  { id: 'sp1', name: 'Hot & Sour Soup', description: 'Tofu, wood ear mushrooms, bamboo shoots, and egg.', price: '$10', category: 'soup', spicy: true, glutenFree: true },
  { id: 'sp2', name: 'Wonton Soup', description: 'Shrimp wontons in a rich chicken broth with scallions.', price: '$10', category: 'soup' },
  { id: 'sp3', name: 'Egg Drop Soup', description: 'Wispy beaten eggs in boiled chicken broth.', price: '$8', category: 'soup', glutenFree: true },
  { id: 'sp4', name: 'West Lake Beef Soup', description: 'Minced beef chowder with cilantro and egg white.', price: '$12', category: 'soup', glutenFree: true },
  { id: 'sp5', name: 'Corn & Chicken Soup', description: 'Creamy corn soup with minced chicken.', price: '$10', category: 'soup', glutenFree: true },
  { id: 'sp6', name: 'Seafood Tofu Soup', description: 'Medley of shrimp, scallops, and squid with silken tofu.', price: '$14', category: 'soup', glutenFree: true },
  { id: 'sp7', name: 'Herbal Chicken Soup', description: 'Double-boiled black chicken with goji berries and ginseng.', price: '$16', category: 'soup', glutenFree: true },
  { id: 'sp8', name: 'Sichuan Fish Soup', description: 'Fish fillets poached in spicy chili oil broth.', price: '$18', category: 'soup', spicy: true },

  // --- Mains ---
  { id: 'mn1', name: 'Peking Duck', description: 'Crispy skin duck served with pancakes, cucumber, scallion and hoisin sauce.', price: '$45', category: 'main' },
  { id: 'mn2', name: 'Kung Pao Chicken', description: 'Spicy stir-fry with chicken, peanuts, vegetables, and chili peppers.', price: '$18', category: 'main', spicy: true },
  { id: 'mn3', name: 'Mapo Tofu', description: 'Silken tofu set in a spicy chili-and-bean-based sauce with minced pork (optional).', price: '$16', category: 'main', spicy: true, glutenFree: true },
  { id: 'mn4', name: 'Sweet & Sour Pork', description: 'Classic Cantonese style with pineapple and bell peppers.', price: '$19', category: 'main' },
  { id: 'mn5', name: 'General Tso\'s Chicken', description: 'Deep-fried chicken in a sweet and spicy sauce.', price: '$18', category: 'main', spicy: true },
  { id: 'mn6', name: 'Mongolian Beef', description: 'Sliced beef flank stir-fried with scallions and onions.', price: '$20', category: 'main' },
  { id: 'mn7', name: 'Honey Walnut Shrimp', description: 'Crispy shrimp with candied walnuts and creamy sauce.', price: '$22', category: 'main', glutenFree: true },
  { id: 'mn8', name: 'Sizzling Black Pepper Beef', description: 'Tender beef tenderloin served on a hot iron plate.', price: '$21', category: 'main', spicy: true },
  { id: 'mn9', name: 'Crispy Pork Belly', description: 'Slow-roasted pork belly with crackling skin and mustard dip.', price: '$19', category: 'main', glutenFree: true },
  { id: 'mn10', name: 'Braised Eggplant', description: 'Eggplant simmered in savory garlic sauce.', price: '$16', category: 'main', vegetarian: true },
  { id: 'mn11', name: 'Salt & Pepper Calamari', description: 'Fried squid tossed with garlic, chili, and five-spice salt.', price: '$18', category: 'main', spicy: true },
  { id: 'mn12', name: 'Szechuan Boiled Fish', description: 'Poached fish fillets in fiery chili oil broth with bean sprouts.', price: '$24', category: 'main', spicy: true, glutenFree: true },
  { id: 'mn13', name: 'Cantonese Roast Duck', description: 'Succulent roast duck with plum sauce.', price: '$26', category: 'main' },
  { id: 'mn14', name: 'Ma Po Eggplant', description: 'Spicy braised eggplant with minced pork.', price: '$17', category: 'main', spicy: true },
  { id: 'mn15', name: 'Beef with Broccoli', description: 'Stir-fried beef and fresh broccoli in oyster sauce.', price: '$19', category: 'main' },
  { id: 'mn16', name: 'Lemon Chicken', description: 'Battered chicken breast with tangy lemon glaze.', price: '$18', category: 'main' },
  { id: 'mn17', name: 'Yangzhou Fried Rice', description: 'Wok-tossed rice with shrimp, BBQ pork, egg, and peas.', price: '$15', category: 'main', glutenFree: true },
  { id: 'mn18', name: 'Lo Mein', description: 'Stir-fried egg noodles with vegetables and choice of meat.', price: '$14', category: 'main' },
  { id: 'mn19', name: 'Singapore Noodles', description: 'Curry-flavored rice vermicelli with shrimp and BBQ pork.', price: '$16', category: 'main', spicy: true, glutenFree: true },
  { id: 'mn20', name: 'Beef Chow Fun', description: 'Wide rice noodles stir-fried with beef, bean sprouts, and scallions.', price: '$17', category: 'main', glutenFree: true },

  // --- Dessert ---
  { id: 'dsrt1', name: 'Mango Sago', description: 'Chilled mango cream with sago pearls and pomelo.', price: '$9', category: 'dessert', vegetarian: true, glutenFree: true },
  { id: 'dsrt2', name: 'Egg Tarts', description: 'Flaky pastry shell filled with warm egg custard.', price: '$8', category: 'dessert', vegetarian: true },
  { id: 'dsrt3', name: 'Sesame Balls', description: 'Fried glutinous rice balls filled with red bean paste.', price: '$6', category: 'dessert', vegetarian: true, glutenFree: true },
  { id: 'dsrt4', name: 'Red Bean Soup', description: 'Hot dessert soup made from azuki beans and dried tangerine peel.', price: '$6', category: 'dessert', vegetarian: true, glutenFree: true },
  { id: 'dsrt5', name: 'Fried Milk', description: 'Deep-fried milk custard, crispy outside and soft inside.', price: '$9', category: 'dessert', vegetarian: true },
  { id: 'dsrt6', name: 'Almond Jelly', description: 'Refreshing almond tofu with fruit cocktail.', price: '$7', category: 'dessert', vegetarian: true, glutenFree: true },
  { id: 'dsrt7', name: 'Durian Puff', description: 'Flaky pastry filled with creamy durian paste.', price: '$10', category: 'dessert', vegetarian: true },
  { id: 'dsrt8', name: 'Ginger Milk Curd', description: 'Hot milk solidified with ginger juice.', price: '$8', category: 'dessert', vegetarian: true, glutenFree: true },
];

const Menu: React.FC<MenuProps> = ({ bannerVisible = false }) => {
  const [activeCategory, setActiveCategory] = useState<'all' | 'dimsum' | 'main' | 'soup' | 'dessert'>('all');
  const menuRef = useRef<HTMLElement>(null);

  // Define category display order and labels
  const categoryConfig = [
    { id: 'dimsum', label: 'Dim Sum' },
    { id: 'soup', label: 'Soups' },
    { id: 'main', label: 'Mains & Rice' },
    { id: 'dessert', label: 'Desserts' },
  ] as const;

  // Determine which categories to show based on selection
  const visibleCategories = activeCategory === 'all' 
    ? categoryConfig 
    : categoryConfig.filter(c => c.id === activeCategory);

  const handleCategoryChange = (category: typeof activeCategory) => {
    setActiveCategory(category);
    
    if (menuRef.current) {
      const stickyOffset = bannerVisible ? 130 : 80;
      const menuTop = menuRef.current.getBoundingClientRect().top + window.scrollY;
      
      if (window.scrollY > menuTop - stickyOffset + 100) {
        window.scrollTo({
          top: menuTop - stickyOffset,
          behavior: 'smooth'
        });
      }
    }
  };

  return (
    <section id="menu" ref={menuRef} className="py-24 bg-[#fdfbf7] relative">
      {/* Background Pattern */}
      <div className="absolute inset-0 opacity-[0.03] pointer-events-none" 
           style={{ backgroundImage: 'radial-gradient(#8B0000 1px, transparent 1px)', backgroundSize: '32px 32px' }}>
      </div>

      <div className="container mx-auto px-4 relative z-10">
        <div className="text-center mb-8">
          <span className="text-primary font-bold uppercase tracking-widest text-xs md:text-sm flex items-center justify-center gap-2">
            <span className="h-[1px] w-8 bg-primary"></span>
            Culinary Excellence
            <span className="h-[1px] w-8 bg-primary"></span>
          </span>
          <h2 className="text-4xl md:text-6xl font-serif font-bold text-foreground mt-4 mb-6">Our Menu</h2>
          <p className="text-gray-500 max-w-2xl mx-auto font-light italic">
            Traditional recipes passed down through generations, prepared with modern precision.
          </p>
        </div>

        {/* Sticky Category Navigation */}
        <div className={`sticky z-40 py-4 mb-12 transition-all duration-300 bg-[#fdfbf7]/95 backdrop-blur-sm shadow-sm -mx-4 px-4 flex justify-center ${bannerVisible ? 'top-[100px]' : 'top-[50px]'}`}>
          <div className="flex flex-wrap justify-center gap-2 md:gap-6">
            <button
              onClick={() => handleCategoryChange('all')}
              className={`relative px-4 py-2 md:px-6 md:py-2 text-xs md:text-sm uppercase tracking-widest transition-all duration-300 border rounded-full ${
                activeCategory === 'all' 
                  ? 'border-primary bg-primary text-white shadow-md' 
                  : 'border-gray-200 bg-white text-gray-500 hover:border-secondary hover:text-foreground'
              }`}
            >
              All Menu
            </button>
            {categoryConfig.map((cat) => (
              <button
                key={cat.id}
                onClick={() => handleCategoryChange(cat.id as any)}
                className={`relative px-4 py-2 md:px-6 md:py-2 text-xs md:text-sm uppercase tracking-widest transition-all duration-300 border rounded-full ${
                  activeCategory === cat.id 
                    ? 'border-primary bg-primary text-white shadow-md' 
                    : 'border-gray-200 bg-white text-gray-500 hover:border-secondary hover:text-foreground'
                }`}
              >
                {cat.label}
              </button>
            ))}
          </div>
        </div>

        {/* Menu Card Container */}
        <div className="max-w-5xl mx-auto relative">
            {/* Card Shadow Layer */}
            <div className="absolute top-4 left-4 w-full h-full bg-gray-200 rounded-sm transform rotate-1"></div>
            
            {/* Main Card */}
            <div className="bg-[#fffbf0] relative shadow-2xl rounded-sm overflow-hidden">
                
                {/* Inner Decorative Border */}
                <div className="absolute inset-3 md:inset-4 border border-secondary/30 pointer-events-none"></div>
                <div className="absolute inset-4 md:inset-6 border border-dotted border-secondary/30 pointer-events-none"></div>

                {/* Corner Ornaments */}
                <div className="absolute top-4 left-4 w-8 h-8 border-t-2 border-l-2 border-secondary z-20"></div>
                <div className="absolute top-4 right-4 w-8 h-8 border-t-2 border-r-2 border-secondary z-20"></div>
                <div className="absolute bottom-4 left-4 w-8 h-8 border-b-2 border-l-2 border-secondary z-20"></div>
                <div className="absolute bottom-4 right-4 w-8 h-8 border-b-2 border-r-2 border-secondary z-20"></div>

                <div className="p-8 md:p-16 relative z-10">
                    
                    {/* Featured Dish Section (Always show at top if All or Main selected) */}
                    {(activeCategory === 'all' || activeCategory === 'main') && (
                    <div className="mb-16 pb-12 border-b border-secondary/20">
                        <div className="flex flex-col md:flex-row items-center gap-8 md:gap-12">
                            <div className="w-full md:w-5/12 shrink-0 overflow-hidden rounded-sm shadow-md relative group">
                                <div className="absolute inset-0 bg-black/10 group-hover:bg-transparent transition-colors duration-500"></div>
                                <img 
                                src="https://picsum.photos/seed/duck4/600/400" 
                                alt="Peking Duck" 
                                className="w-full h-64 md:h-72 object-cover transform group-hover:scale-105 transition-transform duration-1000"
                                />
                            </div>
                            <div className="flex-1 text-center md:text-left">
                                <div className="flex items-center justify-center md:justify-start gap-2 text-secondary mb-3">
                                    <Star className="w-4 h-4 fill-current" />
                                    <span className="text-xs font-bold uppercase tracking-widest">Chef's Recommendation</span>
                                </div>
                                <h3 className="text-3xl md:text-4xl font-serif font-bold text-foreground mb-4">Imperial Peking Duck</h3>
                                <p className="text-gray-600 mb-6 leading-relaxed font-light">
                                    Our signature dish, prepared over 24 hours. Crispy, amber-toned skin served with hand-made pancakes, julienned cucumber, scallion, and our secret hoisin blend.
                                </p>
                                <div className="flex items-center justify-center md:justify-start gap-4">
                                    <span className="text-2xl font-serif font-bold text-primary">$45</span>
                                    <button className="text-xs uppercase tracking-widest border-b border-foreground pb-1 hover:text-primary hover:border-primary transition-colors">
                                        Reserve Now
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    )}

                    {/* Loop through categories to render sections */}
                    {visibleCategories.map((catConfig) => {
                      // Filter items for this category
                      // Exclude the Featured Dish ID (mn1/Peking Duck) from the grid to avoid duplicate
                      const categoryItems = MENU_ITEMS.filter(
                        item => item.category === catConfig.id && item.id !== 'mn1'
                      );
                      
                      const isSparse = categoryItems.length <= 4;

                      return (
                        <div key={catConfig.id} className="mb-16 last:mb-0">
                          {/* Category Header - Always visible */}
                          <div className="flex items-center justify-center gap-4 mb-10">
                              <div className="h-[1px] w-12 bg-secondary/40"></div>
                              <div className="flex flex-col items-center">
                                <h3 className="text-2xl font-serif font-bold text-foreground">{catConfig.label}</h3>
                                <span className="text-xs text-gray-400 uppercase tracking-widest mt-1">Selection</span>
                              </div>
                              <div className="h-[1px] w-12 bg-secondary/40"></div>
                          </div>

                          {/* Grid */}
                          <div className={`grid gap-x-16 gap-y-10 ${
                              // If sparse and filtered to single category, center it.
                              // If viewing 'all', keep grid layout unless extremely sparse
                              (activeCategory !== 'all' && isSparse) 
                                  ? 'grid-cols-1 max-w-2xl mx-auto text-center md:text-left' 
                                  : 'grid-cols-1 lg:grid-cols-2'
                          }`}>
                              {categoryItems.map((item) => (
                                  <div key={item.id} className="group relative break-inside-avoid">
                                      {/* Item Header */}
                                      <div className={`flex items-end justify-between mb-2 ${(activeCategory !== 'all' && isSparse) ? 'md:justify-between' : ''}`}>
                                          <div className="flex items-center gap-2 max-w-[70%]">
                                              <h4 className="text-lg md:text-xl font-serif font-bold text-foreground group-hover:text-primary transition-colors leading-tight">
                                                  {item.name}
                                              </h4>
                                              <div className="flex shrink-0 gap-1">
                                                  {item.spicy && <Flame className="w-4 h-4 text-red-500" aria-label="Spicy" />}
                                                  {item.vegetarian && <Leaf className="w-4 h-4 text-green-600" aria-label="Vegetarian" />}
                                                  {item.glutenFree && <WheatOff className="w-4 h-4 text-amber-600" aria-label="Gluten Free" />}
                                              </div>
                                          </div>
                                          
                                          {/* Dotted Leader */}
                                          <div className="hidden xs:block flex-grow mx-3 border-b-2 border-dotted border-gray-300 relative -top-1.5 opacity-40"></div>
                                          
                                          <span className="text-lg md:text-xl font-serif font-bold text-secondary shrink-0">{item.price}</span>
                                      </div>
                                      
                                      {/* Description */}
                                      <p className="text-gray-500 text-sm font-light leading-relaxed">
                                          {item.description}
                                      </p>
                                  </div>
                              ))}
                          </div>
                        </div>
                      );
                    })}

                    {/* Legend */}
                    <div className="mt-16 pt-8 border-t border-secondary/20 flex flex-wrap justify-center gap-8 text-xs md:text-sm text-gray-400 uppercase tracking-widest">
                        <div className="flex items-center gap-2">
                            <Flame className="w-4 h-4 text-red-500" />
                            <span>Spicy</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <Leaf className="w-4 h-4 text-green-600" />
                            <span>Vegetarian</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <WheatOff className="w-4 h-4 text-amber-600" />
                            <span>Gluten Free</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
      </div>
    </section>
  );
};

export default Menu;