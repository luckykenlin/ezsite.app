import React from 'react';
import { CalendarCheck } from 'lucide-react';

const ReservationCTA: React.FC = () => {
  const handleScrollToReservation = () => {
    const element = document.getElementById('contact');
    if (element) {
        // Header offset calculation (approx 100px for sticky nav)
        const offset = 100;
        const elementPosition = element.getBoundingClientRect().top;
        const offsetPosition = elementPosition + window.scrollY - offset;

        window.scrollTo({
            top: offsetPosition,
            behavior: "smooth"
        });
    }
  };

  return (
    <section className="relative py-24 bg-primary text-white overflow-hidden">
      {/* Decorative background pattern */}
      <div className="absolute inset-0 opacity-10" style={{ backgroundImage: 'radial-gradient(circle, #D4AF37 1px, transparent 1px)', backgroundSize: '30px 30px' }}></div>
      
      <div className="container mx-auto px-4 text-center relative z-10">
        <span className="block text-secondary text-sm md:text-base uppercase tracking-[0.2em] mb-4">
          Experience Authentic Flavors
        </span>
        <h2 className="text-4xl md:text-5xl font-serif font-bold mb-6 text-white">
          A Taste You'll Remember
        </h2>
        <p className="text-background/90 text-lg md:text-xl mb-10 max-w-2xl mx-auto leading-relaxed">
          Whether it's an intimate dinner for two or a family celebration, we have the perfect table waiting for you.
        </p>
        
        <button 
          onClick={handleScrollToReservation}
          className="group inline-flex items-center gap-3 px-10 py-4 bg-secondary text-foreground font-bold uppercase tracking-widest hover:bg-white transition-all duration-300 transform hover:-translate-y-1 shadow-lg"
        >
          <CalendarCheck className="w-5 h-5" />
          <span>Book a Table</span>
        </button>
      </div>
    </section>
  );
};

export default ReservationCTA;