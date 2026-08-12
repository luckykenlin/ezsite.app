import React from 'react';

const About: React.FC = () => {
  return (
    <section id="about" className="py-20 md:py-32 bg-background">
      <div className="container mx-auto px-4">
        <div className="flex flex-col md:flex-row items-center gap-12 lg:gap-20">
          
          {/* Image Grid */}
          <div className="w-full md:w-1/2 relative">
            <div className="grid grid-cols-2 gap-4">
              <img 
                src="https://picsum.photos/seed/dimsum/600/800" 
                alt="Chef cooking" 
                className="w-full h-64 md:h-80 object-cover rounded-lg shadow-xl mt-12"
              />
              <img 
                src="https://picsum.photos/seed/restaurant-interior/600/800" 
                alt="Restaurant Interior" 
                className="w-full h-64 md:h-80 object-cover rounded-lg shadow-xl"
              />
            </div>
            {/* Decorative Element */}
            <div className="absolute -z-10 top-0 right-0 w-48 h-48 bg-secondary/20 rounded-full blur-3xl"></div>
          </div>

          {/* Text Content */}
          <div className="w-full md:w-1/2 space-y-6">
            <div className="flex items-center gap-2">
              <div className="h-0.5 w-12 bg-primary"></div>
              <span className="text-primary font-bold uppercase tracking-widest text-sm">Our Story</span>
            </div>
            <h2 className="text-4xl md:text-5xl font-serif font-bold text-foreground">
              Crafting Memories Since 1995
            </h2>
            <p className="text-gray-600 leading-relaxed text-lg">
              Nestled in the heart of the city, The Golden Lotus began with a simple vision: to bring the authentic, soulful flavors of Guangdong to your table. Our founder, Chef Wei, believes that food is not just sustenance, but a bridge between cultures.
            </p>
            <p className="text-gray-600 leading-relaxed text-lg">
              We source our ingredients locally where possible, and import our specialized spices directly from the markets of Guangzhou to ensure every bite is genuine. From our hand-folded dumplings to our wok-tossed masterpieces, every dish tells a story.
            </p>
            
            <div className="pt-6">
              <img 
                src="https://picsum.photos/seed/signature/200/100" 
                alt="Signature" 
                className="h-16 opacity-70" 
                style={{ filter: 'contrast(0) sepia(100%) hue-rotate(-50deg) saturate(500%)' }} // Fake signature look
              />
              <p className="font-serif italic text-gray-500 mt-2">Chef Wei, Head Chef</p>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
};

export default About;